package id.webprint.bridge.data

import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class PosNotificationApiClient {

    private val client = HttpClientProvider.client

    fun fetchNotifications(settings: BridgeSettings): List<PosNotification> {
        val httpUrl = "${settings.baseUrl}/api/pos-notifications"
            .toHttpUrlOrNull()
            ?: return emptyList()

        val url = httpUrl.newBuilder()
            .addQueryParameter("token", settings.token)
            .addQueryParameter("outlet_id", settings.outletId.toString())
            .apply {
                if (settings.deviceId.isNotBlank()) {
                    addQueryParameter("device_id", settings.deviceId)
                }
            }
            .build()

        val request = Request.Builder()
            .url(url)
            .header("Accept", "application/json")
            .get()
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                return emptyList()
            }
            val body = response.body?.string().orEmpty()
            if (body.isBlank()) return emptyList()

            val json = JSONObject(body)
            val notifArray = json.optJSONArray("notifications") ?: return emptyList()

            return buildList(notifArray.length()) {
                for (i in 0 until notifArray.length()) {
                    val item = notifArray.optJSONObject(i) ?: continue
                    add(PosNotification.fromJson(item))
                }
            }
        }
    }

    fun markRead(settings: BridgeSettings, notificationId: Long) {
        val httpUrl = "${settings.baseUrl}/api/pos-notifications/$notificationId/read"
            .toHttpUrlOrNull()
            ?: return

        val url = httpUrl.newBuilder()
            .addQueryParameter("token", settings.token)
            .build()

        val emptyBody = "{}".toRequestBody("application/json; charset=utf-8".toMediaType())
        val request = Request.Builder()
            .url(url)
            .header("Accept", "application/json")
            .post(emptyBody)
            .build()

        client.newCall(request).execute().use { response ->
            // consumed
        }
    }
}

