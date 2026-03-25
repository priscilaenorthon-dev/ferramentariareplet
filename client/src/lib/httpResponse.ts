type ApiMessagePayload = {
  message?: string;
};

function isJsonContentType(contentType: string | null): boolean {
  return typeof contentType === "string" && contentType.includes("application/json");
}

export async function readApiResponse<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return null as T;
  }

  const contentType = response.headers.get("content-type");
  if (isJsonContentType(contentType)) {
    return (await response.json()) as T;
  }

  const text = await response.text();
  return text as T;
}

export async function readApiError(response: Response, fallbackMessage: string): Promise<Error> {
  const payload = await readApiResponse<ApiMessagePayload | string>(response);

  if (typeof payload === "string") {
    const message = payload.trim();
    return new Error(message || fallbackMessage);
  }

  return new Error(payload?.message || fallbackMessage);
}
