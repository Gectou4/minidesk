import { useEffect, useState } from 'react';
import { getTicket } from '../api.js';
import { shortDate, ticketRef } from '../labels.js';
import { PriorityBadge, StatusBadge } from './Badge.jsx';
import Modal from './Modal.jsx';

export default function TicketDetail({ id, onClose }) {
  const [ticket, setTicket] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    const ctrl = new AbortController();
    getTicket(id, ctrl.signal).then(setTicket).catch((e) => e.name !== 'AbortError' && setError(e.message));
    return () => ctrl.abort();
  }, [id]);

  return (
    <Modal title={`Ticket ${ticketRef(id)}`} onClose={onClose}>
      <div className="modal-body">
        {error && <div className="alert alert-danger mb-0">{error}</div>}
        {!error && !ticket && <p className="mb-0">Chargement...</p>}
        {ticket && (
          <>
            <h3 className="fs-5">{ticket.title}</h3>
            <p className="d-flex gap-2">
              <StatusBadge value={ticket.status} />
              <PriorityBadge value={ticket.priority} />
            </p>
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
