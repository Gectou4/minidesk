// Pagination Bootstrap : précédent, fenêtre de pages autour de la page courante, suivant.
export default function Pagination({ page, pageCount, onChange }) {
  if (pageCount <= 1) return null;
  const first = Math.max(1, Math.min(page - 2, pageCount - 4));
  const last = Math.min(pageCount, first + 4);
  const pages = Array.from({ length: last - first + 1 }, (_, i) => first + i);

  return (
    <nav aria-label="Pagination des tickets">
      <ul className="pagination pagination-sm justify-content-center mb-0">
        <li className={`page-item ${page === 1 ? 'disabled' : ''}`}>
          <button className="page-link" onClick={() => onChange(page - 1)}>Précédent</button>
        </li>
        {pages.map((p) => (
          <li key={p} className={`page-item ${p === page ? 'active' : ''}`}>
            <button className="page-link" onClick={() => onChange(p)}>{p}</button>
          </li>
        ))}
        <li className={`page-item ${page === pageCount ? 'disabled' : ''}`}>
          <button className="page-link" onClick={() => onChange(page + 1)}>Suivant</button>
        </li>
      </ul>
    </nav>
  );
}
