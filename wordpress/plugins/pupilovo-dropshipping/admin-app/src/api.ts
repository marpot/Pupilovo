declare global {
  interface Window {
    pupilovoSupplierHub: {
      restUrl: string;
      nonce: string;
      adminUrl: string;
      version: string;
    };
  }
}

interface ApiErrorBody {
  message?: string;
  code?: string;
}

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
  }
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const isFormData = init.body instanceof FormData;
  const response = await fetch(`${window.pupilovoSupplierHub.restUrl}${path}`, {
    credentials: 'same-origin',
    ...init,
    headers: {
      Accept: 'application/json',
      'X-WP-Nonce': window.pupilovoSupplierHub.nonce,
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...init.headers,
    },
  });

  const body = await response.json().catch(() => ({})) as T & ApiErrorBody;
  if (!response.ok) {
    throw new ApiError(body.message || 'Nie udało się wykonać operacji.', response.status);
  }

  return body;
}
