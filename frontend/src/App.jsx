import { useCallback, useEffect, useState } from 'react';
import { listTickets } from './api.js';
import { PRIORITIES, STATUSES } from './labels.js';
import NewTicketModal from './components/NewTicketModal.jsx';
import Pagination from './components/Pagination.jsx';
import Telemetry from './components/Telemetry.jsx';
import TicketDetail from './components/TicketDetail.jsx';
import TicketTable from './components/TicketTable.jsx';

const PAGE_SIZE = 10;
const STATUS_KEYS = Object.keys(STATUSES);

export default function App() {
  const [filters, setFilters] = useState({ status: '', priority: '' });
  const [page, setPage] = useState(1);
  const [data, setData] = useState({ items: [], total: 0 });
  const [counts, setCounts] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedId, setSelectedId] = useState(null);
  const [showNew, setShowNew] = useState(false);
  const [reload, setReload] = useState(0);

  // Liste paginée : relancée à chaque changement de filtre, de page ou après une création
  useEffect(() => {
    const ctrl = new AbortController();
    setLoading(true);
    listTickets({ ...filters, limit: PAGE_SIZE, offset: (page - 1) * PAGE_SIZE }, ctrl.signal)
      .then((d) => { setData(d); setError(null); })
      .catch((e) => e.name !== 'AbortError' && setError(e.message))
      .finally(() => setLoading(false));
    return () => ctrl.abort();
  }, [filters, page, reload]);

  // Compteurs de télémétrie : un appel limit=1 par statut, le total vient de X-Total-Count
  useEffect(() => {
    Promise.all([
      listTickets({ limit: 1 }),
      ...STATUS_KEYS.map((status) => listTickets({ status, limit: 1 })),
    ])
      .then(([all, ...byStatus]) => {
        setCounts({ all: all.total, ...Object.fromEntries(STATUS_KEYS.map((s, i) => [s, byStatus[i].total])) });
      })
      .catch(() => setCounts(null));
  }, [reload]);

  const changeFilter = (field) => (e) => {
    setFilters({ ...filters, [field]: e.target.value });
    setPage(1);
  };

  const onCreated = useCallback(() => {
    setShowNew(false);
    setPage(1);
    setReload((n) => n + 1);
  }, []);

  const pageCount = Math.max(1, Math.ceil(data.total / PAGE_SIZE));

  return (
    <div className="container py-4">
      <header className="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <h1 className="brand mb-0">MiniDesk</h1>
          <div className="text-body-secondary mono">Centre de contrôle des tickets</div>
        </div>
        <button className="btn btn-primary" onClick={() => setShowNew(true)}>+ Nouveau ticket</button>
      </header>

      <Telemetry counts={counts} />

      <section className="panel">
        <div className="row g-2 p-3 border-bottom panel-line">
          <div className="col-6 col-md-3">
            <select className="form-select form-select-sm" aria-label="Filtrer par statut"
                    value={filters.status} onChange={changeFilter('status')}>
              <option value="">Tous les statuts</option>
              {Object.entries(STATUSES).map(([v, s]) => <option key={v} value={v}>{s.label}</option>)}
            </select>
          </div>
          <div className="col-6 col-md-3">
            <select className="form-select form-select-sm" aria-label="Filtrer par priorité"
                    value={filters.priority} onChange={changeFilter('priority')}>
              <option value="">Toutes les priorités</option>
              {Object.entries(PRIORITIES).map(([v, p]) => <option key={v} value={v}>{p.label}</option>)}
            </select>
          </div>
          <div className="col-12 col-md-6 text-md-end text-body-secondary mono small align-self-center">
            {data.total} ticket{data.total > 1 ? 's' : ''} · page {page}/{pageCount}
          </div>
        </div>

        {error && <div className="alert alert-danger m-3">{error}</div>}
        <div className={loading ? 'opacity-50' : ''}>
          <TicketTable tickets={data.items} onSelect={setSelectedId} />
        </div>
        <div className="p-3 border-top panel-line">
          <Pagination page={page} pageCount={pageCount} onChange={setPage} />
        </div>
      </section>

      {selectedId && <TicketDetail id={selectedId} onClose={() => setSelectedId(null)} />}
      {showNew && <NewTicketModal onClose={() => setShowNew(false)} onCreated={onCreated} />}
    </div>
  );
}
