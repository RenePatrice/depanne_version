import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Le technicien qui avance vers chez le client, sur OpenStreetMap (#7).
///
/// Instantané à l'ouverture (`/position-technicien`), puis chaque position
/// poussée sur `ticket.{id}`. Sans WebSocket, une relecture toutes les quinze
/// secondes prend le relais. Le serveur ne sert la position que pendant le
/// trajet et si elle est fraîche : quand il n'en a pas, la carte le dit au
/// lieu d'inventer un point.
///
/// La distance affichée est **à vol d'oiseau**, et annoncée comme telle : un
/// temps d'arrivée calculé sans le trafic de Conakry serait une promesse que
/// personne ne tient.
class CarteSuivi extends ConsumerStatefulWidget {
  const CarteSuivi({
    required this.ticketId,
    required this.destination,
    super.key,
  });

  final int ticketId;
  final LatLng? destination;

  @override
  ConsumerState<CarteSuivi> createState() => _CarteSuiviState();
}

class _CarteSuiviState extends ConsumerState<CarteSuivi> {
  static const Duration _repli = Duration(seconds: 15);
  static const LatLng _conakry = LatLng(9.6412, -13.5784);

  final MapController _carte = MapController();
  PositionTechnicien? _position;
  Timer? _minuteur;
  bool _charge = false;
  bool _suiviManuel = false;

  @override
  void initState() {
    super.initState();
    _relire();
    _minuteur = Timer.periodic(_repli, (_) => _relire());
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    _carte.dispose();
    super.dispose();
  }

  Future<void> _relire() async {
    try {
      final PositionTechnicien? p = await ref
          .read(clientRepositoryProvider)
          .positionTechnicien(widget.ticketId);
      if (!mounted) return;
      _appliquer(p);
    } on ApiException {
      // Une relecture manquée n'est pas une panne : la suivante réessaiera.
    } finally {
      if (mounted && !_charge) setState(() => _charge = true);
    }
  }

  void _appliquer(PositionTechnicien? p) {
    setState(() => _position = p);
    if (p != null && !_suiviManuel) {
      _cadrer();
    }
  }

  void _cadrer() {
    final PositionTechnicien? p = _position;
    final LatLng? d = widget.destination;
    if (p == null) return;

    final LatLng tech = LatLng(p.latitude, p.longitude);

    try {
      if (d == null) {
        _carte.move(tech, 15);
      } else {
        _carte.fitCamera(
          CameraFit.bounds(
            bounds: LatLngBounds(tech, d),
            padding: const EdgeInsets.all(56),
            maxZoom: 16,
          ),
        );
      }
    } on Object {
      // La carte n'est pas encore dessinée : le premier rendu cadrera.
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    ref.listen(signauxProvider('ticket.${widget.ticketId}'), (
      _,
      AsyncValue<EvenementTempsReel> signal,
    ) {
      final EvenementTempsReel? e = signal.value;
      if (e == null || !e.estPosition) return;
      final Object? lat = e.donnees['latitude'];
      final Object? lng = e.donnees['longitude'];
      if (lat is num && lng is num) {
        _appliquer(
          PositionTechnicien(
            latitude: lat.toDouble(),
            longitude: lng.toDouble(),
            le: DateTime.now(),
          ),
        );
      }
    });

    final PositionTechnicien? p = _position;
    final LatLng? tech = p == null ? null : LatLng(p.latitude, p.longitude);
    final LatLng? dest = widget.destination;
    final double? km = tech == null || dest == null
        ? null
        : const Distance().as(LengthUnit.Meter, tech, dest) / 1000;

    return ClipRRect(
      borderRadius: PcRadius.lgAll,
      child: SizedBox(
        height: 250,
        child: Stack(
          children: <Widget>[
            FlutterMap(
              mapController: _carte,
              options: MapOptions(
                initialCenter: tech ?? dest ?? _conakry,
                initialZoom: 14,
                minZoom: 10,
                maxZoom: 18,
                onMapReady: _cadrer,
                onPositionChanged: (MapCamera _, bool parGeste) {
                  if (parGeste && !_suiviManuel) {
                    setState(() => _suiviManuel = true);
                  }
                },
              ),
              children: <Widget>[
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'gn.depannemoi.app',
                  maxZoom: 18,
                ),
                if (tech != null && dest != null)
                  PolylineLayer(
                    polylines: <Polyline>[
                      Polyline(
                        points: <LatLng>[tech, dest],
                        strokeWidth: 3,
                        color: pc.accent.withValues(alpha: 0.55),
                        pattern: StrokePattern.dashed(
                          segments: const <double>[8, 6],
                        ),
                      ),
                    ],
                  ),
                MarkerLayer(
                  markers: <Marker>[
                    if (dest != null)
                      Marker(
                        point: dest,
                        width: 40,
                        height: 40,
                        child: _Repere(
                          icone: Icons.home_rounded,
                          couleur: PcColors.coral,
                        ),
                      ),
                    if (tech != null)
                      Marker(
                        point: tech,
                        width: 46,
                        height: 46,
                        child: _Repere(
                          icone: Icons.two_wheeler_rounded,
                          couleur: pc.accent,
                          grand: true,
                        ),
                      ),
                  ],
                ),
                const RichAttributionWidget(
                  attributions: <SourceAttribution>[
                    TextSourceAttribution('© OpenStreetMap'),
                  ],
                ),
              ],
            ),
            Positioned(
              left: 12,
              top: 12,
              right: 12,
              child: Row(
                children: <Widget>[
                  Flexible(
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: pc.card,
                        borderRadius: BorderRadius.circular(99),
                        boxShadow: pc.ombre,
                      ),
                      child: Text(
                        !_charge
                            ? 'Localisation du technicien…'
                            : km == null
                            ? 'Position du technicien pas encore reçue'
                            : '${km.toStringAsFixed(1)} km à vol d’oiseau',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: pc.ink,
                          fontWeight: FontWeight.w700,
                          fontSize: 12.5,
                        ),
                      ),
                    ),
                  ),
                  if (_suiviManuel && tech != null) ...<Widget>[
                    const SizedBox(width: 8),
                    PcIconButton(
                      icone: Icons.center_focus_strong_rounded,
                      libelle: 'Recentrer',
                      onTap: () {
                        setState(() => _suiviManuel = false);
                        _cadrer();
                      },
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Repere extends StatelessWidget {
  const _Repere({
    required this.icone,
    required this.couleur,
    this.grand = false,
  });

  final IconData icone;
  final Color couleur;
  final bool grand;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: couleur,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: PcShadow.glow(couleur),
      ),
      child: Icon(icone, color: Colors.white, size: grand ? 22 : 18),
    );
  }
}
