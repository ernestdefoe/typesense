import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

declare const m: import('mithril').Static;

const K = 'ernestdefoe-typesense';

app.initializers.add(K, () => {
  // The forum document lists which search resources are routed to Typesense
  // (booleans only — connection details never reach the browser). Show a
  // small badge in the search modal footer on exactly those tabs.
  //
  // SearchModal is lazy-loaded (app.modal.show(() => import(...))), so extend
  // it by module path — core applies the extension via flarum.reg.onLoad once
  // the chunk arrives. Extending the prototype directly would run before the
  // module exists and silently do nothing.
  extend(
    'flarum/common/components/SearchModal',
    'activeTabItems',
    function (this: any, items: ItemList<Mithril.Children>) {
      const typesenseResources = (app.forum.attribute<string[]>('typesenseSearch') ||
        []) as string[];
      const source = this.activeSource?.();

      if (!source || !typesenseResources.includes(source.resource)) return;

      items.add(
        'typesense',
        m('div', { className: 'SearchModal-section TypesenseBadge-section' }, [
          m('span', { className: 'TypesenseBadge' }, [
            m(
              'span',
              { className: 'TypesenseBadge-icon', 'aria-hidden': 'true' },
              m('i', { className: 'fas fa-bolt-lightning' })
            ),
            app.translator.trans(`${K}.forum.powered_by`),
          ]),
        ]),
        0
      );
    }
  );
});
