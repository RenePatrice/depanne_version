import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Clé Google Maps Android.
//
// Elle vit dans `android/local.properties`, qui n'est pas versionné, ou dans la
// variable d'environnement `MAPS_API_KEY` pour une machine de compilation. Une
// clé écrite dans le dépôt est une clé publique : un APK se décompile, mais un
// dépôt Git se lit sans même cet effort.
//
// Absente, la valeur reste vide : l'application compile et fonctionne, seule la
// carte embarquée ne s'affiche pas. C'est voulu — le pilote n'a pas encore de
// compte Google Cloud, et rien du parcours n'en dépend.
val mapsApiKey: String = run {
    val local = rootProject.file("local.properties")

    if (local.exists()) {
        val proprietes = Properties()
        local.inputStream().use(proprietes::load)
        proprietes.getProperty("MAPS_API_KEY")
    } else {
        null
    } ?: System.getenv("MAPS_API_KEY") ?: ""
}

// Signature de release (#11, D5).
//
// Les identifiants du keystore vivent dans `android/key.properties`, qui n'est
// pas versionne (comme le keystore lui-meme) : un secret dans le depot est un
// secret public. Absent, on retombe sur la cle de debug pour que
// `flutter run --release` fonctionne sur une machine de developpement sans
// keystore -- l'APK n'est alors pas publiable, mais il tourne.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val aUnKeystore = keystorePropertiesFile.exists()
if (aUnKeystore) {
    keystorePropertiesFile.inputStream().use(keystoreProperties::load)
}

android {
    namespace = "gn.depannemoi.app"
    compileSdk = flutter.compileSdkVersion

    // Le plugin Gradle de Flutter impose cette version, meme sans une ligne de
    // C dans le projet : la retirer d'ici ne fait que la lui laisser choisir.
    // Le NDK doit donc etre installe -- 2,5 Go :
    //
    //   android sdk install ndk/28.2.13676358
    //
    // A ne pas installer via "sdkmanager", ni en laissant AGP le faire : le
    // shim deprecie plante sur cette machine (NTSTATUS 0xC0000409), et le
    // build echoue sur une erreur qui ne parle pas du NDK.
    ndkVersion = flutter.ndkVersion


    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "gn.depannemoi.app"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        manifestPlaceholders["MAPS_API_KEY"] = mapsApiKey
    }

    signingConfigs {
        if (aUnKeystore) {
            create("release") {
                keyAlias = keystoreProperties.getProperty("keyAlias")
                keyPassword = keystoreProperties.getProperty("keyPassword")
                storeFile = keystoreProperties.getProperty("storeFile")?.let { file(it) }
                storePassword = keystoreProperties.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            // Signee avec la vraie cle si key.properties est present, sinon
            // avec la cle de debug (pour que `flutter run --release` marche).
            signingConfig = if (aUnKeystore) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }

            // Durcissement R8 (#11) : reduction, obscurcissement et elagage des
            // ressources. Le code Dart est en plus obscurci au build via
            // --obfuscate (voir scripts/build-release.cmd) ; ici on traite la
            // couche Android/Kotlin et on retire le code mort.
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
