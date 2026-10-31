// Habillage « mission spatiale » des valeurs de l'API (les valeurs envoyées restent celles de l'API).

export const STATUSES = {
  open: { label: 'En attente', cls: 'st-open' },
  in_progress: { label: 'En vol', cls: 'st-flight' },
  closed: { label: 'Atterri', cls: 'st-landed' },
};

export const PRIORITIES = {
  low: { label: 'Nominal', cls: 'pr-low' },
  medium: { label: 'Vigilance', cls: 'pr-medium' },
  high: { label: 'Alerte', cls: 'pr-high' },
  critical: { label: 'Critique', cls: 'pr-critical' },
};

export const ticketRef = (id) => `MD-${String(id).padStart(4, '0')}`;

// "2026-10-30 14:05:09" -> "2026-10-30 14:05"
export const shortDate = (s) => (s ?? '').slice(0, 16);
