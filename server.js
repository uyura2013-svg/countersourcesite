const express = require("express");
const session = require("express-session");
const multer = require("multer");
const path = require("path");
const fs = require("fs");

const app = express();
const PORT = process.env.PORT || 3000;
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD;
const SESSION_SECRET = process.env.SESSION_SECRET;

if (!ADMIN_PASSWORD || !SESSION_SECRET) {
  console.error("Set ADMIN_PASSWORD and SESSION_SECRET environment variables.");
  process.exit(1);
}

const uploadDir = path.join(__dirname, "uploads");
fs.mkdirSync(uploadDir, { recursive: true });

app.use(express.json({ limit: "10kb" }));
app.use(session({
  name: "cs_admin",
  secret: SESSION_SECRET,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: "strict",
    secure: process.env.NODE_ENV === "production",
    maxAge: 2 * 60 * 60 * 1000
  }
}));
app.use(express.static(path.join(__dirname, "public")));

function requireAdmin(req, res, next) {
  if (req.session && req.session.admin === true) return next();
  return res.status(401).json({ error: "Требуется вход в админ-панель." });
}

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, uploadDir),
  filename: (_req, file, cb) => {
    const safe = path.basename(file.originalname).replace(/[^a-zA-Z0-9._-]/g, "_");
    cb(null, `${Date.now()}-${safe}`);
  }
});
const upload = multer({
  storage,
  limits: { fileSize: 1024 * 1024 * 1024, files: 1 },
  fileFilter: (_req, file, cb) => {
    if (path.extname(file.originalname).toLowerCase() !== ".apk") {
      return cb(new Error("Разрешены только файлы .apk"));
    }
    cb(null, true);
  }
});

app.post("/api/login", (req, res) => {
  if (!req.body || req.body.password !== ADMIN_PASSWORD) {
    return res.status(401).json({ error: "Неверный пароль." });
  }
  req.session.admin = true;
  res.json({ ok: true });
});

app.post("/api/logout", (req, res) => {
  req.session.destroy(() => res.json({ ok: true }));
});

app.get("/api/status", (req, res) => res.json({ loggedIn: !!(req.session && req.session.admin) }));

app.post("/api/upload", requireAdmin, upload.single("apk"), (req, res) => {
  if (!req.file) return res.status(400).json({ error: "Файл не выбран." });
  const meta = {
    name: req.file.originalname,
    stored: req.file.filename,
    size: req.file.size,
    uploadedAt: new Date().toISOString()
  };
  fs.writeFileSync(path.join(uploadDir, "latest.json"), JSON.stringify(meta, null, 2));
  res.json({ ok: true, file: meta, download: "/download" });
});

app.get("/api/file", (_req, res) => {
  const metaPath = path.join(uploadDir, "latest.json");
  if (!fs.existsSync(metaPath)) return res.json({ available: false });
  const meta = JSON.parse(fs.readFileSync(metaPath, "utf8"));
  res.json({ available: true, name: meta.name, size: meta.size, uploadedAt: meta.uploadedAt });
});

app.get("/download", (_req, res) => {
  const metaPath = path.join(uploadDir, "latest.json");
  if (!fs.existsSync(metaPath)) return res.status(404).send("APK пока не загружен.");
  const meta = JSON.parse(fs.readFileSync(metaPath, "utf8"));
  const filePath = path.join(uploadDir, meta.stored);
  if (!fs.existsSync(filePath)) return res.status(404).send("Файл не найден.");
  res.download(filePath, meta.name);
});

app.use((err, _req, res, _next) => {
  const msg = err.code === "LIMIT_FILE_SIZE" ? "Файл больше лимита 1 ГБ." : err.message || "Ошибка загрузки.";
  res.status(400).json({ error: msg });
});

app.listen(PORT, () => console.log(`CounterSource site listening on port ${PORT}`));
