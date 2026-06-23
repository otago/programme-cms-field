// app/programmes/client/js/programmeid-dropdown/state.js

(function (root, $) {
  root.OPProg = root.OPProg || {};
  const KEY = 'programmeidDropdownState';

  function ensure($el) {
    let s = $el.data(KEY);
    if (!s) {
      s = {
        endpoint: '/programme-options/list',
        pageSize: 25,
        minLen: 3,
        q: '',
        offset: 0,
        hasNext: false,
        busy: false,
      };
      $el.data(KEY, s);
    }
    return s;
  }
  function set($el, name, value) {
    const s = ensure($el);
    s[name] = value;
    $el.data(KEY, s);
  }
  function get($el, name) {
    return ensure($el)[name];
  }
  function clear($el) {
    $el.removeData(KEY);
  }

  root.OPProg.prgState = { ensure, set, get, clear };
})(window, jQuery);
