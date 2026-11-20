import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Apparence choisie par l'utilisateur. Le clair est le défaut.
enum Apparence {
  clair('clair', 'Clair', ThemeMode.light),
  sombre('sombre', 'Sombre', ThemeMode.dark),
  systeme('systeme', 'Comme le téléphone', ThemeMode.system);

  const Apparence(this.cle, this.libelle, this.themeMode);

  final String cle;
  final String libelle;
  final ThemeMode themeMode;

  static Apparence depuisCle(String? cle) => Apparence.values.firstWhere(
    (Apparence a) => a.cle == cle,
    orElse: () => Apparence.clair,
  );
}

abstract interface class ApparenceStore {
  Future<String?> lire();

  Future<void> ecrire(String cle);
}

class SecureApparenceStore implements ApparenceStore {
  const SecureApparenceStore([this._coffre = const FlutterSecureStorage()]);

  static const String _cle = 'parema.apparence';

  final FlutterSecureStorage _coffre;

  @override
  Future<String?> lire() async {
    try {
      return await _coffre.read(key: _cle);
    } on Object {
      return null;
    }
  }

  @override
  Future<void> ecrire(String cle) async {
    try {
      await _coffre.write(key: _cle, value: cle);
    } on Object {
      // Préférence d'affichage : la perdre n'est pas grave.
    }
  }
}

class MemoryApparenceStore implements ApparenceStore {
  String? _cle;

  @override
  Future<String?> lire() async => _cle;

  @override
  Future<void> ecrire(String cle) async => _cle = cle;
}

final Provider<ApparenceStore> apparenceStoreProvider =
    Provider<ApparenceStore>((Ref ref) => const SecureApparenceStore());

class ApparenceController extends Notifier<Apparence> {
  @override
  Apparence build() {
    Future<void>.microtask(_charger);
    return Apparence.clair;
  }

  Future<void> _charger() async {
    final Apparence memorisee = Apparence.depuisCle(
      await ref.read(apparenceStoreProvider).lire(),
    );
    if (memorisee != state) state = memorisee;
  }

  Future<void> choisir(Apparence apparence) async {
    state = apparence;
    await ref.read(apparenceStoreProvider).ecrire(apparence.cle);
  }
}

final NotifierProvider<ApparenceController, Apparence> apparenceProvider =
    NotifierProvider<ApparenceController, Apparence>(ApparenceController.new);
