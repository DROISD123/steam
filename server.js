// server.js
import express from "express";
import fetch from "node-fetch";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// (opsional) kalau file statis ada di /public
app.use(express.static(path.join(__dirname, "public")));

// ---- Helpers dengan cc & l bisa di-set, default Indonesia ----
async function fetchAppDetails(appid, cc = "id", l = "indonesian") {
  const url = `https://store.steampowered.com/api/appdetails?appids=${appid}&cc=${cc}&l=${l}`;
  const r = await fetch(url, { timeout: 10000 });
  return r.json();
}
async function fetchStoreSearch(q, cc = "id", l = "indonesian") {
  const url = `https://store.steampowered.com/api/storesearch/?term=${encodeURIComponent(q)}&cc=${cc}&l=${l}`;
  const r = await fetch(url, { timeout: 10000 });
  return r.json();
}
async function fetchFeatured(cc = "id", l = "indonesian") {
  const url = `https://store.steampowered.com/api/featuredcategories/?cc=${cc}&l=${l}`;
  const r = await fetch(url, { timeout: 10000 });
  return r.json();
}

// ---- Endpoints ----
app.get("/json", async (req, res) => {
  const id = req.query.id;
  const cc = req.query.cc || "id";
  const l  = req.query.l  || "indonesian";
  if (!id) return res.status(400).json({ error: "parameter id diperlukan, contohnya /json?id=570" });
  try {
    const data = await fetchAppDetails(id, cc, l);
    if (data?.[id]?.success) {
      res.setHeader("Content-Type", "application/json; charset=utf-8");
      res.send(JSON.stringify(data[id].data, null, 2));
    } else {
      res.status(404).json({ error: "app not found", raw: data });
    }
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get("/api/appdetails", async (req, res) => {
  const id = req.query.id;
  const cc = req.query.cc || "id";
  const l  = req.query.l  || "indonesian";
  if (!id) return res.status(400).json({ error: "parameter id diperlukan" });
  try {
    const data = await fetchAppDetails(id, cc, l);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get("/api/search", async (req, res) => {
  const q  = req.query.q;
  const cc = req.query.cc || "id";
  const l  = req.query.l  || "indonesian";
  if (!q) return res.status(400).json({ error: "parameter q diperlukan" });
  try {
    const data = await fetchStoreSearch(q, cc, l);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// 🔥 Baru: featured/event Steam via server Indonesia
app.get("/api/featured", async (req, res) => {
  const cc = req.query.cc || "id";
  const l  = req.query.l  || "indonesian";
  try {
    const data = await fetchFeatured(cc, l);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});
// === Proxy cek branch GitHub dengan token ===
const GH_OWNER = "SteamAutoCracks";
const GH_REPO  = "ManifestHub";
const BRANCH_CACHE = new Map();

async function githubBranchExists(id) {
  // Cache 10 menit
  const cache = BRANCH_CACHE.get(id);
  if (cache && (Date.now() - cache.ts) < 10 * 60 * 1000) return cache.ok;

  const url = `https://api.github.com/repos/${GH_OWNER}/${GH_REPO}/branches/${id}`;
  const headers = {
    "User-Agent": "ManifestHub-Server",
    "Accept": "application/vnd.github+json",
    "Authorization": `Bearer ${process.env.GITHUB_TOKEN || ""}`
  };

  let ok = false;
  try {
    const r = await fetch(url, { headers });
    if (r.status === 200) {
      ok = true;
    } else if (r.status === 404) {
      ok = false;
    } else {
      // Fallback ke codeload
      const head = await fetch(
        `https://codeload.github.com/${GH_OWNER}/${GH_REPO}/zip/refs/heads/${id}`,
        { method: "HEAD" }
      );
      ok = head.status === 200;
    }
  } catch (err) {
    console.warn("GitHub check error:", err.message);
    ok = false;
  }

  BRANCH_CACHE.set(id, { ok, ts: Date.now() });
  return ok;
}

// API endpoint untuk dipanggil dari frontend
app.get("/api/zip-exists", async (req, res) => {
  const id = req.query.id;
  if (!id) return res.status(400).json({ error: "parameter id diperlukan" });

  try {
    const exists = await githubBranchExists(id);
    res.json({ exists });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get("/health", (_req, res) => res.json({ status: "ok" }));

app.listen(PORT, "0.0.0.0", () => {
  console.log(`✅ Server berjalan di port ${PORT}`);
});
