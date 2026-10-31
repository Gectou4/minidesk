import { STATUSES } from '../labels.js';

export default function Telemetry({ counts }) {
  const cards = [
    { key: 'all', label: 'Total', cls: 'st-all', value: counts?.all },
    ...Object.entries(STATUSES).map(([key, s]) => ({ key, label: s.label, cls: s.cls, value: counts?.[key] })),
  ];
  return (
    <div className="row g-3 mb-4">
      {cards.map((c) => (
        <div className="col-6 col-md-3" key={c.key}>
          <div className={`telemetry ${c.cls}`}>
            <div className="telemetry-label">{c.label}</div>
            <div className="telemetry-value">{c.value ?? '--'}</div>
          </div>
        </div>
      ))}
    </div>
  );
}
