// Client de l'API MiniDesk. Chemins relatifs : même origine que le front.

export class ApiError extends Error {
  constructor(message, status, details = {}) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

async function request(path, options = {}) {
  let res;
  try {
    res = await fetch(path, {
      ...options,
      headers: { Accept: 'application/json', ...(options.body && { 'Content-Type': 'application/json' }) },
    });
  } catch {
    throw new ApiError('Liaison perdue avec l\'API.', 0);
  }
  const body = await res.json().catch(() => null);
  if (!res.ok) {
    throw new ApiError(body?.error ?? `Erreur ${res.status}`, res.status, body?.details);
  }
  return { body, res };
}

/** Liste paginée : le total (hors pagination) est dans l'en-tête X-Total-Count. */
export async function listTickets({ status = '', priority = '', limit, offset = 0 }, signal) {
  const params = new URLSearchParams({ limit, offset });
  if (status) params.set('status', status);
  if (priority) params.set('priority', priority);
  const { body, res } = await request(`/tickets?${params}`, { signal });
  return { items: body, total: Number(res.headers.get('X-Total-Count') ?? body.length) };
}

export async function getTicket(id, signal) {
  return (await request(`/tickets/${id}`, { signal })).body;
}

export async function createTicket(data) {
  return (await request('/tickets', { method: 'POST', body: JSON.stringify(data) })).body;
}
