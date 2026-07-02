import app from 'flarum/admin/app';
import TypesensePage from './components/TypesensePage';

app.initializers.add('ernestdefoe-typesense', () => {
  app.extensionData.for('ernestdefoe-typesense').registerPage(TypesensePage);
});
