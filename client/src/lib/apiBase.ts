const envBase = (import.meta.env.VITE_API_BASE as string | undefined) ?? "";

const defaultBase =
  typeof window !== "undefined"
    ? window.location.origin
    : "http://localhost:5000";

const base = envBase.trim() !== "" ? envBase : defaultBase;

function normalize(path: string): string {
  if (path.startsWith("http")) return path;
  const prefix = base.endsWith("/") ? base.slice(0, -1) : base;
  const suffix = path.startsWith("/") ? path : `/${path}`;
  return `${prefix}${suffix}`;
}

export function apiUrl(path: string): string {
  return normalize(path);
}
