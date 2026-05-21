const API_BASE = "/client-api";

export function apiUrl(path = "") {
    const cleanPath = String(path).replace(/^\/+/, "");
    return `${API_BASE}/${cleanPath}`;
}
