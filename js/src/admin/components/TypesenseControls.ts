import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';

declare const m: import('mithril').Static;

const K = 'ernestdefoe-typesense';
// Core stores the per-model search driver under `search_driver_<ModelClass>`;
// the backslashes are literal in the stored key.
const DRIVERS: { key: string; label: string }[] = [
  { key: 'search_driver_Flarum\\Discussion\\Discussion', label: 'use_for_discussions' },
  { key: 'search_driver_Flarum\\User\\User', label: 'use_for_users' },
  { key: 'search_driver_Flarum\\Post\\Post', label: 'use_for_posts' },
];
const t = (k: string) => app.translator.trans(`${K}.admin.${k}`);

interface Attrs {
  setting: (k: string, d?: string) => (v?: string) => string;
}

export default class TypesenseControls extends Component<Attrs> {
  testing = false;
  testStatus: 'ok' | 'fail' | null = null;
  testError = '';
  rebuilding = false;

  view() {
    const setting = this.attrs.setting;
    const apiKey = setting(`${K}.api_key`, '');

    return m('div', [
      m('.Form-group', [
        m('label', t('api_key_label')),
        m('input.FormControl', {
          type: 'password',
          value: apiKey(),
          oninput: (e: InputEvent) => apiKey((e.target as HTMLInputElement).value),
          placeholder: '••••••••',
        }),
      ]),

      m('hr'),

      m('.Form-group', [
        m('label', t('drivers_label')),
        m('.helpText', t('drivers_help')),
        ...DRIVERS.map(({ key, label }) => {
          const driver = setting(key, 'database');
          return m(
            'div',
            { style: 'margin: 6px 0' },
            m(
              Switch,
              {
                state: driver() === 'typesense',
                onchange: (v: boolean) => driver(v ? 'typesense' : 'database'),
              },
              t(label)
            )
          );
        }),
      ]),

      m('hr'),

      m('.Form-group', [
        m('label', t('connection_label')),
        m('div', [
          m(Button, { className: 'Button', loading: this.testing, onclick: () => this.test() }, t('test_button')),
          ' ',
          this.testStatus === 'ok'
            ? m('span', { style: 'color: var(--success-color, green)' }, ['✓ ', t('test_ok')])
            : this.testStatus === 'fail'
            ? m('span', { style: 'color: var(--error-color, #d83e3e)' }, ['✗ ', t('test_fail'), this.testError ? ` (${this.testError})` : ''])
            : null,
        ]),
      ]),

      m('.Form-group', [
        m('label', t('rebuild_label')),
        m('.helpText', t('rebuild_help')),
        m(Button, { className: 'Button', loading: this.rebuilding, onclick: () => this.rebuild() }, t('rebuild_button')),
      ]),
    ]);
  }

  apiUrl(path: string): string {
    return `${app.forum.attribute('apiUrl')}${path}`;
  }

  test() {
    this.testing = true;
    this.testStatus = null;
    this.testError = '';

    app
      .request<{ ok?: boolean; error?: string }>({ method: 'GET', url: this.apiUrl('/typesense/status') })
      .then((res) => {
        this.testStatus = res && res.ok ? 'ok' : 'fail';
        this.testError = (res && res.error) || '';
      })
      .catch(() => {
        this.testStatus = 'fail';
      })
      .then(() => {
        this.testing = false;
        m.redraw();
      });
  }

  rebuild() {
    this.rebuilding = true;

    app
      .request({ method: 'POST', url: this.apiUrl('/typesense/rebuild') })
      .then(() => app.alerts.show({ type: 'success' }, t('rebuild_queued')))
      .catch(() => app.alerts.show({ type: 'error' }, t('rebuild_failed')))
      .then(() => {
        this.rebuilding = false;
        m.redraw();
      });
  }
}
