import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../data/technicien_repository.dart';
import 'technicien_providers.dart';

/// Remontée de la position du technicien (§10).
///
/// C'est la pièce que la carte live du back-office attend depuis B6 : elle
/// émet exactement l'événement que `php artisan demo:positions` simulait, et
/// le jour où l'application tourne, la simulation s'arrête sans rien changer
/// côté supervision.
///
/// Trois règles portent tout le reste :
///
/// - **On ne suit que quand il y a une raison de suivre.** Une position n'est
///   utile qu'à deux moments : pour être trouvé par le matching quand on est
///   en ligne, et pour être suivi par le client pendant une intervention. En
///   dehors, un GPS actif vide la batterie et collecte une donnée personnelle
///   dont personne n'a l'usage.
/// - **Le rythme suit l'enjeu.** Toutes les huit secondes pendant une
///   intervention, où le client regarde le point avancer ; toutes les deux
///   minutes en simple disponibilité, où seule la zone compte.
/// - **Un échec ne remonte jamais à l'écran.** Le réseau de Conakry coupe, le
///   GPS met parfois trente secondes à accrocher. Une position perdue est un
///   désagrément ; une exception qui casse l'écran d'intervention est une
///   panne.
class PositionService {
  PositionService(this._depot);

  final TechnicienRepository _depot;

  /// Pendant une intervention : le client regarde le point avancer.
  static const Duration cadenceIntervention = Duration(seconds: 8);

  /// En simple disponibilité : seule la zone compte, et le matching relit la
  /// dernière position connue si elle a moins de quinze minutes.
  static const Duration cadenceDisponible = Duration(minutes: 2);

  /// Sous ce seuil, on ne renvoie rien : le technicien n'a pas bougé, et le
  /// serveur réécrirait la même ligne.
  static const int filtreMetres = 20;

  StreamSubscription<Position>? _flux;
  Timer? _minuteur;
  Position? _derniere;
  bool _envoiEnCours = false;

  bool get suitLaPosition => _flux != null;

  /// Démarre le suivi, ou l'ajuste s'il tourne déjà à une autre cadence.
  ///
  /// Renvoie `false` quand la permission manque ou que le service de
  /// localisation est éteint : l'appelant peut alors l'expliquer, ce qu'il
  /// vaut mieux faire que de laisser croire que tout va bien.
  Future<bool> demarrer({required bool enIntervention}) async {
    if (!await _autorise()) return false;

    await arreter();

    _flux =
        Geolocator.getPositionStream(
          locationSettings: LocationSettings(
            accuracy: enIntervention
                ? LocationAccuracy.high
                : LocationAccuracy.medium,
            distanceFilter: filtreMetres,
          ),
        ).listen(
          (Position p) => _derniere = p,
          // Le flux peut tomber — GPS coupé, permission retirée en cours de
          // route. On garde la dernière position connue et on laisse le
          // minuteur continuer : elle vaut mieux que rien.
          onError: (Object _) {},
        );

    _minuteur = Timer.periodic(
      enIntervention ? cadenceIntervention : cadenceDisponible,
      (_) => _remonter(),
    );

    // Une première remontée tout de suite : attendre huit secondes pour
    // apparaître sur la carte du client donne l'impression d'un technicien qui
    // n'est pas parti.
    unawaited(_remonter());

    return true;
  }

  Future<void> arreter() async {
    _minuteur?.cancel();
    _minuteur = null;
    await _flux?.cancel();
    _flux = null;
  }

  Future<void> _remonter() async {
    final Position? p = _derniere;

    // Deux envois ne se chevauchent pas : sur un réseau lent, la file
    // grossirait plus vite qu'elle ne se vide, et le serveur recevrait des
    // positions périmées dans le désordre.
    if (p == null || _envoiEnCours) return;

    _envoiEnCours = true;

    try {
      await _depot.remonterPosition(
        latitude: p.latitude,
        longitude: p.longitude,
      );
    } on Object catch (_) {
      // Silence volontaire : voir l'en-tête de la classe.
    } finally {
      _envoiEnCours = false;
    }
  }

  /// Service actif et permission accordée.
  ///
  /// La permission est demandée ici, au moment où elle sert — pas au premier
  /// lancement. Quelqu'un à qui on demande sa position avant de lui avoir
  /// montré pourquoi refuse, et le refus définitif est difficile à rattraper.
  Future<bool> _autorise() async {
    if (!await Geolocator.isLocationServiceEnabled()) return false;

    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    return permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse;
  }
}

final Provider<PositionService> positionServiceProvider =
    Provider<PositionService>((Ref ref) {
      final PositionService service = PositionService(
        ref.watch(technicienRepositoryProvider),
      );

      ref.onDispose(service.arreter);

      return service;
    });
