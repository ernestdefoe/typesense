import app from 'flarum/admin/app';
import ExtensionPage, { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import m from 'mithril';

// Core stores the per-model search driver under `search_driver_<ModelClass>`.
// The backslashes are literal in the stored key.
const DISCUSSION_DRIVER_KEY = 'search_driver_Flarum\\Discussion\\Discussion';

export default class TypesensePage extends ExtensionPage<ExtensionPageAttrs> {
  testing = false;
  testStatus: 'ok' | 'fail' | null = null;
  testError = '';
  rebuilding = false;

  content() {
    const useTypesense = this.setting(DISCUSSION_DRIVER_KEY)() === 'typesense';

    return m('.ExtensionPage-settings', m('.container', [
      m('p.helpText', app.translator.trans('ernestdefoe-typesense.admin.intro')),

      this.field('host', 'text', '127.0.0.1'),
      this.field('port', 'text', '8108'),

      m('.Form-group', [
        m('label', app.translator.trans('ernestdefoe-typesense.admin.protocol_label')),
        this.buildSettingComponent({
          type: 'select',
          setting: 'ernestdefoe-typesense.protocol',
          options: { http: 'HTTP', https: 'HTTPS' },
          default: 'http',
        }),
      ]),

      this.field('api_key', 'password', ''),
      this.field('collection_prefix', 'text', ''),
      m('p.helpText', app.translator.trans('ernestdefoe-typesense.admin.prefix_help')),

      m('.Form-group', [
        Switch.component(
          {
            state: useTypesense,
            onchange: (val: boolean) => this.setting(DISCUSSION_DRIVER_KEY)(val ? 'typesense' : 'database'),
          },
          app.translator.trans('ernestdefoe-typesense.admin.use_for_discussions')
        ),
        m('p.helpText', app.translator.trans('ernestdefoe-typesense.admin.use_for_discussions_help')),
      ]),

      m('.Form-group', this.submitButton()),

      m('hr'),

      m('.Form-group', [
        m('label', app.translator.trans('ernestdefoe-typesense.admin.connection_label')),
        m('div', [
          Button.component(
            { className: 'Button', loading: this.testing, onclick: () => this.testConnection() },
            app.translator.trans('ernestdefoe-typesense.admin.test_button')
          ),
          ' ',
          this.testStatus === 'ok'
            ? m('span', { style: 'color: var(--success-color, green)' }, ['✓ ', app.translator.trans('ernestdefoe-typesense.admin.test_ok')])
            : this.testStatus === 'fail'
            ? m('span', { style: 'color: var(--error-color, #d83e3e)' }, ['✗ ', app.translator.trans('ernestdefoe-typesense.admin.test_fail'), this.testError ? ` (${this.testError})` : ''])
            : null,
        ]),
      ]),

      m('.Form-group', [
        m('label', app.translator.trans('ernestdefoe-typesense.admin.rebuild_label')),
        m('p.helpText', app.translator.trans('ernestdefoe-typesense.admin.rebuild_help')),
        Button.component(
          { className: 'Button', loading: this.rebuilding, onclick: () => this.rebuild() },
          app.translator.trans('ernestdefoe-typesense.admin.rebuild_button')
        ),
      ]),
    ]));
  }

  field(key: string, type: string, placeholder: string) {
    return m('.Form-group', [
      m('label', app.translator.trans(`ernestdefoe-typesense.admin.${key}_label`)),
      this.buildSettingComponent({ type, setting: `ernestdefoe-typesense.${key}`, placeholder }),
    ]);
  }

  apiUrl(path: string): string {
    return `${app.forum.attribute('apiUrl')}${path}`;
  }

  testConnection() {
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
      .then(() => {
        app.alerts.show({ type: 'success' }, app.translator.trans('ernestdefoe-typesense.admin.rebuild_queued'));
      })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('ernestdefoe-typesense.admin.rebuild_failed'));
      })
      .then(() => {
        this.rebuilding = false;
        m.redraw();
      });
  }
}
