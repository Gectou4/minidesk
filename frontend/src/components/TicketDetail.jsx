import { useEffect, useState } from 'react';
import { getTicket, updateTicketField } from '../api.js';
import { PRIORITIES, STATUSES, shortDate, ticketRef } from '../labels.js';
import Modal from './Modal.jsx';

export default function TicketDetail({ id, onClose, onChanged }) {
  const [ticket, setTicket] = useState(null);
  const [error, setError] = useState(null);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const ctrl = new AbortController();
    getTicket(id, ctrl.signal).then(setTicket).catch((e) => e.name !== 'AbortError' && setError(e.message));
    return () => ctrl.abort();
  }, [id]);

  // Enregistre dès que la valeur change, puis prévient la liste pour qu'elle se rafraîchisse
  const update = (field) => async (e) => {
    setSaving(true);
    setError(null);
    try {
      setTicket(await updateTicketField(id, field, e.target.value));
      onChanged();
    } catch (err) {
      setError(err.details?.[field] ?? err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal title={`Ticket ${ticketRef(id)}`} onClose={onClose}>
      <div className="modal-body">
        {error && <div className="alert alert-danger">{error}</div>}
        {!ticket && !error && <p className="mb-0">Chargement...</p>}
        {ticket && (
          <>
            <h3 className="fs-5">{ticket.title}</h3>
            <div className="row g-3 mb-3">
              <div className="col-6">
                <label className="form-label" htmlFor="d-status">Statut</label>
                <select id="d-status" className={`form-select ${STATUSES[ticket.status]?.cls}`}
                        value={ticket.status} onChange={update('status')} disabled={saving}>
                  {Object.entries(STATUSES).map(([v, s]) => <option key={v} value={v}>{s.label}</option>)}
                </select>
              </div>
              <div className="col-6">
                <label className="form-label" htmlFor="d-priority">Priorité</label>
                <select id="d-priority" className={`form-select ${PRIORITIES[ticket.priority]?.cls}`}
                        value={ticket.priority} onChange={update('priority')} disabled={saving}>
                  {Object.entries(PRIORITIES).map(([v, p]) => <option key={v} value={v}>{p.label}</option>)}
                </select>
              </div>
            </div>
            <p style={{ whiteSpace: 'pre-wrap' }}>{ticket.description || <em>Aucune description.</em>}</p>
            <small className="text-body-secondary mono">
              Créé le {shortDate(ticket.created_at)} · mis à jour le {shortDate(ticket.updated_at)}
            </small>
          </>
        )}
      </div>
    </Modal>
  );
}
