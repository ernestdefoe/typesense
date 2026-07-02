import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';
import TypesenseControls from './components/TypesenseControls';

declare const m: import('mithril').Static;

const K = 'ernestdefoe-typesense';
const t = (k: string) => app.translator.trans(`${K}.admin.${k}`);

// Each .setting()/.customSetting() takes a FUNCTION (the Flarum 2 Admin extender
// contract). Standard connection fields are declared here; the API key, driver
// toggle and action buttons live in the custom controls component below so the
// key is masked and the buttons can call the extension's endpoints.
export default [
  new Admin()
    .setting(() => ({
      setting: `${K}.host`,
      type: 'text',
      label: t('host_label'),
      placeholder: '127.0.0.1',
    }))
    .setting(() => ({
      setting: `${K}.port`,
      type: 'text',
      label: t('port_label'),
      placeholder: '8108',
    }))
    .setting(() => ({
      setting: `${K}.protocol`,
      type: 'select',
      options: { http: 'HTTP', https: 'HTTPS' },
      default: 'http',
      label: t('protocol_label'),
    }))
    .setting(() => ({
      setting: `${K}.collection_prefix`,
      type: 'text',
      label: t('collection_prefix_label'),
      help: t('prefix_help'),
    }))
    .customSetting(function (this: { setting: (k: string, d?: string) => (v?: string) => string }) {
      return m(TypesenseControls, { setting: (k: string, d?: string) => this.setting(k, d) });
    }),
];
