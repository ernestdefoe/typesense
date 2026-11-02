// Settings are declared via the JS Admin extender in ./extend — the Flarum 2
// pattern that replaced the removed 1.x `app.extensionData` API (using that API
// throws "failed to initialize"). No initializer is needed.
export { default as extend } from './extend';
