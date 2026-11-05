# Regles R8/ProGuard pour la release (#11).
#
# R8 obscurcit et elague le code Android/Kotlin. Flutter et ses greffons
# exposent des classes appelees par reflexion ou depuis le moteur natif : on
# les garde explicitement, sinon l'APK release plante la ou le debug marchait.

# --- Moteur Flutter ---------------------------------------------------------
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
-keep class io.flutter.embedding.** { *; }
-dontwarn io.flutter.**

# --- Greffons natifs utilises par l'application -----------------------------
# geolocator, flutter_secure_storage, url_launcher, path_provider,
# flutter_map (reseau/tuiles) : code appele par le moteur, a conserver.
-keep class com.baseflow.geolocator.** { *; }
-keep class com.it_nomads.fluttersecurestorage.** { *; }

# --- Divers -----------------------------------------------------------------
# Les annotations et les signatures generiques servent a la serialisation JSON
# cote Dart ; les retirer ne gagne rien et casse parfois la reflexion native.
-keepattributes *Annotation*, Signature, InnerClasses, EnclosingMethod

# Pas de secret ni de cle dans le binaire (#11) : rien a garder ici, c'est un
# rappel. Les cles (Reverb, API, Maps) sont injectees au build, pas ecrites
# dans le code.
