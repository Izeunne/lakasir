package id.webprint.bridge.data

import org.json.JSONObject

data class PosNotification(
    val id: Long,
    val title: String,
    val message: String,
    val type: String,
    val sound: String,
    val data: JSONObject?,
    val createdAt: String?,
) {
    companion object {
        fun fromJson(json: JSONObject): PosNotification {
            return PosNotification(
                id = json.optLong("id", 0L),
                title = json.optString("title", "Notifikasi POS"),
                message = json.optString("message", ""),
                type = json.optString("type", "order"),
                sound = json.optString("sound", "default"),
                data = json.optJSONObject("data"),
                createdAt = if (json.has("created_at")) json.optString("created_at") else null,
            )
        }
    }
}