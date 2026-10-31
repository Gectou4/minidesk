import { shortDate, ticketRef } from '../labels.js';
import { PriorityBadge, StatusBadge } from './Badge.jsx';

export default function TicketTable({ tickets, onSelect }) {
  if (tickets.length === 0) {
    return <p className="text-center text-body-secondary py-5 mb-0">Aucun ticket sur cette trajectoire.</p>;
  }
  return (
    <div className="table-responsive">
      <table className="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Réf.</th><th>Titre</th><th>Statut</th><th>Priorité</th><th>Créé le</th></tr>
        </thead>
        <tbody>
          {tickets.map((t) => (
            <tr key={t.id} role="button" onClick={() => onSelect(t.id)}>
              <td className="mono">{ticketRef(t.id)}</td>
              <td>{t.title}</td>
              <td><StatusBadge value={t.status} /></td>
              <td><PriorityBadge value={t.priority} /></td>
              <td className="mono">{shortDate(t.created_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
