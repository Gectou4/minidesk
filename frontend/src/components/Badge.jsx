import { PRIORITIES, STATUSES } from '../labels.js';

export function StatusBadge({ value }) {
  const s = STATUSES[value];
  return <span className={`badge badge-mission ${s?.cls ?? ''}`}>{s?.label ?? value}</span>;
}

export function PriorityBadge({ value }) {
  const p = PRIORITIES[value];
  return (
    <span className={`badge badge-mission ${p?.cls ?? ''}`}>
      <span className="dot" />
      {p?.label ?? value}
    </span>
  );
}
