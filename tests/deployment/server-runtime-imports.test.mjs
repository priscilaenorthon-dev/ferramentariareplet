import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";

const rootDir = process.cwd();

for (const relativePath of [
  "server/auth.ts",
  "server/authRoutes.ts",
  "server/db.ts",
  "server/routes.ts",
  "server/storage.ts",
]) {
  const source = fs.readFileSync(path.join(rootDir, relativePath), "utf8");

  assert.ok(
    !source.includes("@shared/schema"),
    `${relativePath} should not use tsconfig path aliases at Vercel runtime`,
  );
}
