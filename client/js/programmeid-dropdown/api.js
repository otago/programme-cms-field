// app/programmes/client/js/programmeid-dropdown/api.js

(function (root, $) {
  root.OPProg = root.OPProg || {};
  function fetchJSON(endpoint, params) {
    return $.ajax({ url: endpoint, method: 'GET', dataType: 'json', data: params || {} });
  }
  root.OPProg.prgApi = { fetchJSON };
})(window, jQuery);
