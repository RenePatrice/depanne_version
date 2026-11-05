import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../../../design_system/design_tokens.dart';

/// Sélecteur de point sur carte **OpenStreetMap** (#7), sans clé Google.
///
/// Le client choisit d'abord un quartier (point de départ), puis affine en
/// touchant la carte : la position sert à trouver la zone et à mesurer une
/// distance, toutes deux à l'échelle du kilomètre. Le repère écrit reste ce qui
/// amène réellement quelqu'un devant la porte — la carte ne le remplace pas.
///
/// `flutter_map` est pur Dart : aucune clé, aucun greffon natif. Les tuiles
/// viennent d'OSM, dont la politique d'usage **exige l'attribution** (affichée)
/// et un `User-Agent` identifiant l'application (d'où `userAgentPackageName`).
class SelecteurCarte extends StatefulWidget {
  const SelecteurCarte({
    required this.point,
    required this.onChange,
    super.key,
  });

  final LatLng point;
  final ValueChanged<LatLng> onChange;

  @override
  State<SelecteurCarte> createState() => _SelecteurCarteState();
}

class _SelecteurCarteState extends State<SelecteurCarte> {
  final MapController _controleur = MapController();
  late LatLng _marqueur = widget.point;

  @override
  void didUpdateWidget(SelecteurCarte ancien) {
    super.didUpdateWidget(ancien);

    // Le quartier a changé en amont : on recentre et on repose le marqueur.
    if (widget.point != ancien.point) {
      _marqueur = widget.point;
      _controleur.move(widget.point, _controleur.camera.zoom);
    }
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  void _placer(LatLng point) {
    setState(() => _marqueur = point);
    widget.onChange(point);
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(DmRadius.md),
      child: SizedBox(
        height: 240,
        child: FlutterMap(
          mapController: _controleur,
          options: MapOptions(
            initialCenter: _marqueur,
            initialZoom: 15,
            minZoom: 11,
            maxZoom: 18,
            onTap: (TapPosition _, LatLng point) => _placer(point),
          ),
          children: <Widget>[
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'gn.depannemoi.app',
              maxZoom: 18,
            ),
            MarkerLayer(
              markers: <Marker>[
                Marker(
                  point: _marqueur,
                  width: 44,
                  height: 44,
                  alignment: Alignment.topCenter,
                  child: const Icon(
                    Icons.location_on,
                    color: DmColors.danger,
                    size: 40,
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
      ),
    );
  }
}
