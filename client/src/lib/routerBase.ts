export function getBasePath(): string {
  if (typeof window === "undefined") return "";
  // Usa a pasta onde o index.html está hospedado, removendo a barra final.
  return new URL(".", window.location.href).pathname.replace(/\/$/, "");
}
