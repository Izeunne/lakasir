plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

import java.io.File
import java.util.Properties

val signingProperties = Properties()
val signingPropertiesFile = listOf(
    rootProject.file("signing.properties"),
    rootProject.file("app/signing.properties"),
    file("signing.properties"),
).firstOrNull { it.exists() }

if (signingPropertiesFile != null && signingPropertiesFile.exists()) {
    signingPropertiesFile.inputStream().use(signingProperties::load)
}

fun getSigningProp(key: String): String? {
    return (signingProperties.getProperty(key)
        ?: signingProperties.getProperty("\uFEFF$key")
        ?: signingProperties[key] as? String
        ?: signingProperties["\uFEFF$key"] as? String)?.trim()
}

android {
    namespace = "id.webprint.bridge"
    compileSdk = 34

    defaultConfig {
        applicationId = "id.webprint.bridge"
        minSdk = 26
        targetSdk = 34
        versionCode = 2
        versionName = "1.0.1"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    signingConfigs {
        create("release") {
            if (signingPropertiesFile != null && signingPropertiesFile.exists()) {
                val storeFilePath = getSigningProp("storeFile").orEmpty()
                if (storeFilePath.isNotEmpty()) {
                    val candidateFiles = listOf(
                        File(storeFilePath),
                        rootProject.file(storeFilePath),
                        rootProject.file("app/$storeFilePath"),
                        file(storeFilePath),
                    )
                    val resolvedKeystore = candidateFiles.firstOrNull { it.exists() }
                    if (resolvedKeystore != null) {
                        storeFile = resolvedKeystore
                        storePassword = getSigningProp("storePassword")
                        keyAlias = getSigningProp("keyAlias")
                        keyPassword = getSigningProp("keyPassword")
                    }
                }
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            val releaseSigning = signingConfigs.getByName("release")
            if (releaseSigning.storeFile != null && releaseSigning.storeFile!!.exists()) {
                signingConfig = releaseSigning
            }
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        viewBinding = true
    }

    lint {
        checkReleaseBuilds = false
        abortOnError = false
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("androidx.activity:activity-ktx:1.9.0")
    implementation("androidx.constraintlayout:constraintlayout:2.1.4")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
}
