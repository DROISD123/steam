// server.js
import express from "express";
import fetch from "node-fetch";
import path from "path";
import { fileURLToPath } from "url";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

// serve static frontend
app.use(express.static(path.join(__dirname, "public")));

// Helper: fetch Steam appdetails
async function fetchAppDetails(appid) {
  const url = `https://store.steampowered.com/api/appdetails?appids=${appid}&cc=us`;
  const r = await fetch(url, { timeout: 10000 });
  const json = await r.json();
  return json;
}

// Helper: fetch storesearch
async function fetchStoreSearch(q) {
  const url = `https://store.steampowered.com/api/storesearch/?term=${encodeURIComponent(q)}&cc=us`;
  const r = await fetch(url, { timeout: 10000 });
  const json = await r.json();
  return json;
}

// Public JSON endpoint for a single game (pretty printed)
app.get("/json", async (req, res) => {
  const id = req.query.id;
  if (!id) return res.status(400).json({ error: "parameter id diperlukan, contohnya /json?id=570" });

  try {
    const data = await fetchAppDetails(id);
    // return only the inner data for appid (or full object if not found)
    if (data && data[id] && data[id].success) {
      // pretty JSON
      res.setHeader("Content-Type", "application/json; charset=utf-8");
      res.send(JSON.stringify(data[id].data, null, 2));
    } else {
      res.status(404).json({ error: "app not found", raw: data });
    }
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// API proxy that returns raw Steam appdetails JSON (same structure as Steam)
app.get("/api/appdetails", async (req, res) => {
  const id = req.query.id;
  if (!id) return res.status(400).json({ error: "parameter id diperlukan" });
  try {
    const data = await fetchAppDetails(id);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// API proxy for storesearch (search by name)
app.get("/api/search", async (req, res) => {
  const q = req.query.q;
  if (!q) return res.status(400).json({ error: "parameter q diperlukan" });
  try {
    const data = await fetchStoreSearch(q);
    res.json(data);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

// optional: health check
app.get("/health", (req, res) => res.json({ status: "ok" }));

app.listen(PORT, () => {
  console.log(`✅ Server berjalan di http://localhost:${PORT}`);
  console.log(` - UI: http://localhost:${PORT}/`);
  console.log(` - JSON endpoint: http://localhost:${PORT}/json?id=570`);
});
