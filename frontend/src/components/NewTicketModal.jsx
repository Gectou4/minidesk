import { useState } from 'react';
import { createTicket } from '../api.js';
import { PRIORITIES } from '../labels.js';
import Modal from './Modal.jsx';

export default function NewTicketModal({ onClose, onCreated }) {
  const [form, setForm] = useState({ title: '', description: '', priority: 'medium' });
  const [errors, setErrors] = useState({});
  const [sending, setSending] = useState(false);

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  async function submit(e) {
    e.preventDefault();
    setSending(true);
    setErrors({});
    try {
      await createTicket(form);
      onCreated();
    } catch (err) {
      // 422 : erreurs par champ dans details ; autre erreur : message général
      setErrors(err.details && Object.keys(err.details).length ? err.details : { _: err.message });
      setSending(false);
    }
  }

  const invalid = (f) => (errors[f] ? 'is-invalid' : '');

  return (
    <Modal title="Nouveau ticket" onClose={onClose}>
      <form onSubmit={submit} noValidate>
        <div className="modal-body">
          {errors._ && <div className="alert alert-danger">{errors._}</div>}
          <div className="mb-3">
            <label className="form-label" htmlFor="t-title">Titre</label>
            <input id="t-title" className={`form-control ${invalid('title')}`} value={form.title}
                   onChange={set('title')} maxLength={255} autoFocus />
            <div className="invalid-feedback">{errors.title}</div>
          </div>
          <div className="mb-3">
            <label className="form-label" htmlFor="t-desc">Description</label>
            <textarea id="t-desc" className={`form-control ${invalid('description')}`} rows={4}
                      value={form.description} onChange={set('description')} />
            <div className="invalid-feedback">{errors.description}</div>
          </div>
          <div>
            <label className="form-label" htmlFor="t-prio">Priorité</label>
            <select id="t-prio" className={`form-select ${invalid('priority')}`} value={form.priority}
                    onChange={set('priority')}>
              {Object.entries(PRIORITIES).map(([value, p]) => (
                <option key={value} value={value}>{p.label}</option>
              ))}
            </select>
            <div className="invalid-feedback">{errors.priority}</div>
          </div>
        </div>
        <div className="modal-footer">
          <button type="button" className="btn btn-outline-secondary" onClick={onClose}>Annuler</button>
          <button type="submit" className="btn btn-primary" disabled={sending}>
            {sending ? 'Envoi...' : 'Lancer le ticket'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
