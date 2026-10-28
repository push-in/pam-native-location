package dev.pam.location

import android.app.Activity
import java.io.File
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

/** The "<code>: <detail>" failure contract shared with PHP's LocationError (1.35.0). */
class LocationContractTest {
    @Test
    fun failureCodesMatchPhpLocationErrorInOrder() {
        assertEquals(
            listOf("permission", "disabled", "unavailable", "timeout"),
            LocationFailure.entries.map(LocationFailure::code),
        )
        val php = pluginFile("src/LocationError.php").readText()
        LocationFailure.entries.forEachIndexed { index, failure ->
            val case = failure.name.lowercase().replaceFirstChar(Char::uppercase)
            assertTrue("PHP LocationError::$case = ${index + 1}", php.contains("case $case = ${index + 1};"))
            assertTrue("PHP code() maps $case", php.contains("self::$case => '${failure.code}'"))
        }
    }

    @Test
    fun failurePayloadIsCodeColonDetail() {
        assertEquals(
            "permission: Location permission is required",
            LocationFailure.PERMISSION.payload("Location permission is required").decodeToString(),
        )
        assertEquals(
            "disabled: No enabled location provider",
            LocationFailure.DISABLED.payload("No enabled location provider").decodeToString(),
        )
    }

    @Test
    fun thrownErrorsMapToTheirCode() {
        assertEquals(
            "disabled: Location services are off",
            locationFailurePayload(LocationException(LocationFailure.DISABLED, "Location services are off"))
                .decodeToString(),
        )
        assertEquals(
            "permission: denied",
            locationFailurePayload(SecurityException("denied")).decodeToString(),
        )
        assertEquals(
            "unavailable: Unknown location method x",
            locationFailurePayload(IllegalStateException("Unknown location method x")).decodeToString(),
        )
        assertEquals(
            "unavailable: Location operation failed",
            locationFailurePayload(IllegalStateException()).decodeToString(),
        )
    }

    @Test
    fun servicesResultsAreSequentialAndFollowTheDialog() {
        assertEquals(listOf(1L, 2L, 3L), LocationServicesResult.entries.map(LocationServicesResult::wire))
        assertEquals(LocationServicesResult.ENABLED, LocationServicesResult.fromResolution(Activity.RESULT_OK))
        assertEquals(LocationServicesResult.DENIED, LocationServicesResult.fromResolution(Activity.RESULT_CANCELED))
        val php = pluginFile("src/LocationServicesResult.php").readText()
        assertTrue(php.contains("case Enabled = 1;") && php.contains("case Denied = 2;") && php.contains("case Unavailable = 3;"))
    }

    @Test
    fun cachedFixesHonourMaximumAge() {
        assertTrue(isFreshLocation(timeMs = 10_000, nowMs = 40_000, maximumAgeMs = 30_000))
        assertFalse(isFreshLocation(timeMs = 9_999, nowMs = 40_000, maximumAgeMs = 30_000))
        assertFalse(isFreshLocation(timeMs = 40_000, nowMs = 40_001, maximumAgeMs = 0))
    }

    @Test
    fun pluginShipsTheFusedProvider() {
        val manifest = pluginFile("pam-native.plugin.json").readText()
        assertTrue(manifest.contains("\"com.google.android.gms:play-services-location:"))
    }

    /** The plugin root: `pam.plugin.dir`, else the nearest parent holding pam-native.plugin.json. */
    private fun pluginFile(path: String): File {
        System.getProperty("pam.plugin.dir")?.let { return File(it, path) }
        val starts = listOf(File("").absoluteFile, File(javaClass.protectionDomain.codeSource.location.toURI()))
        for (start in starts) {
            var directory: File? = start
            while (directory != null) {
                if (File(directory, "pam-native.plugin.json").isFile) return File(directory, path)
                directory = directory.parentFile
            }
        }
        return File(path)
    }
}
