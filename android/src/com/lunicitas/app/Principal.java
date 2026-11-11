// LuniCitas para Android: abre lunicitas.com dentro de una app instalable. Licencia AGPL-3.0
// · Recuerda la última página (la de tu negocio o tu agenda) y la abre al tocar el ícono.
// · Los enlaces de lunicitas.com se quedan en la app; WhatsApp, teléfono, mapas y otras páginas se abren fuera.
// · Entrar con Google/Microsoft se hace en el navegador (Google no lo permite dentro de apps) y vuelve a la app.
package com.lunicitas.app;

import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.view.View;
import android.view.ViewGroup;
import android.view.Window;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.Toast;

public class Principal extends Activity {
  static final String DOMINIO = "lunicitas.com";
  static final String BASE = "https://" + DOMINIO;
  static final int ELEGIR_ARCHIVO = 7;

  WebView web;
  ProgressBar barra;
  ValueCallback<Uri[]> archivos;
  SharedPreferences prefs;

  @Override protected void onCreate(Bundle estado) {
    super.onCreate(estado);
    prefs = getSharedPreferences("lunicitas", MODE_PRIVATE);
    Window w = getWindow();
    w.setStatusBarColor(Color.parseColor("#5b45d6"));

    FrameLayout raiz = new FrameLayout(this);
    web = new WebView(this);
    raiz.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
    barra = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
    barra.setMax(100);
    barra.setVisibility(View.GONE);
    raiz.addView(barra, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, (int) (4 * getResources().getDisplayMetrics().density)));
    setContentView(raiz);

    WebSettings s = web.getSettings();
    s.setJavaScriptEnabled(true);
    s.setDomStorageEnabled(true);
    s.setDatabaseEnabled(true);
    s.setAllowFileAccess(false);
    s.setAllowContentAccess(false);
    s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
    s.setUserAgentString(s.getUserAgentString() + " LuniCitasApp/1");
    CookieManager.getInstance().setAcceptCookie(true);
    CookieManager.getInstance().setAcceptThirdPartyCookies(web, false);

    web.setWebViewClient(new Cliente());
    web.setWebChromeClient(new Cromo());
    web.setDownloadListener(new Descargas());

    if (estado != null) web.restoreState(estado);
    else web.loadUrl(destino(getIntent()));
  }

  // Qué abrir: el enlace recibido (si es de LuniCitas), la última página usada o la bienvenida.
  String destino(Intent i) {
    Uri u = i == null ? null : i.getData();
    if (u != null && "https".equals(u.getScheme()) && DOMINIO.equals(u.getHost())) return u.toString();
    return prefs.getString("ultima", BASE + "/app");
  }

  @Override protected void onNewIntent(Intent i) {
    super.onNewIntent(i);
    if (i != null && i.getData() != null) web.loadUrl(destino(i));
  }

  @Override protected void onSaveInstanceState(Bundle b) { super.onSaveInstanceState(b); web.saveState(b); }
  @Override protected void onPause() { super.onPause(); CookieManager.getInstance().flush(); }

  @Override public void onBackPressed() { if (web.canGoBack()) web.goBack(); else super.onBackPressed(); }

  boolean esNuestro(Uri u) { return u != null && "https".equals(u.getScheme()) && DOMINIO.equals(u.getHost()); }

  boolean esPago(Uri u) {
    String h = u.getHost();
    return "https".equals(u.getScheme()) && h != null && (h.equals("stripe.com") || h.endsWith(".stripe.com") || h.endsWith(".stripe.network"));
  }

  void abrirFuera(Uri u) {
    try { startActivity(new Intent(Intent.ACTION_VIEW, u).addCategory(Intent.CATEGORY_BROWSABLE)); }
    catch (ActivityNotFoundException e) { Toast.makeText(this, "No hay ninguna app para abrir este enlace.", Toast.LENGTH_SHORT).show(); }
  }

  // Decide dónde se abre cada enlace.
  boolean decidir(String url) {
    Uri u = Uri.parse(url);
    String esquema = u.getScheme() == null ? "" : u.getScheme();
    if (esNuestro(u)) {
      // Google y Microsoft no permiten entrar desde dentro de una app: se entra en el navegador y se vuelve aquí.
      if ("/oauth.php".equals(u.getPath()) && u.getQueryParameter("p") != null) {
        abrirFuera(u.buildUpon().appendQueryParameter("app", "1").build());
        return true;
      }
      return false;
    }
    if (esPago(u)) return false; // el pago de Stripe se hace dentro de la app
    if (esquema.equals("intent")) {
      try {
        Intent i = Intent.parseUri(url, Intent.URI_INTENT_SCHEME);
        i.addCategory(Intent.CATEGORY_BROWSABLE); i.setComponent(null); i.setSelector(null);
        startActivity(i);
      } catch (Exception e) {
        Toast.makeText(this, "No se pudo abrir el enlace.", Toast.LENGTH_SHORT).show();
      }
      return true;
    }
    if (esquema.equals("http") || esquema.equals("https") || esquema.equals("tel") || esquema.equals("mailto")
        || esquema.equals("sms") || esquema.equals("whatsapp") || esquema.equals("geo") || esquema.equals("market")) {
      abrirFuera(u);
      return true;
    }
    return true; // cualquier otro esquema se bloquea
  }

  // Guarda la página de tu negocio o tu agenda para abrirla la próxima vez.
  void recordar(String url) {
    Uri u = Uri.parse(url);
    if (!esNuestro(u) || u.getPath() == null) return;
    String p = u.getPath();
    boolean agenda = p.equals("/admin.php") || p.equals("/entrar");
    boolean negocio = p.matches("^/[a-z0-9-]{2,40}$")
        && !p.matches("^/(app|entrar|registro|recuperar|contacto|terminos|privacidad|inicio|calendario|offline)$");
    if (agenda) prefs.edit().putString("ultima", BASE + "/admin.php").apply();
    else if (negocio) prefs.edit().putString("ultima", BASE + p).apply();
  }

  void errorDeRed(WebView v) {
    String volver = prefs.getString("ultima", BASE + "/app");
    String html = "<!doctype html><html lang='es'><head><meta name='viewport' content='width=device-width,initial-scale=1'>"
        + "<style>body{font-family:sans-serif;background:#f7f7fb;color:#1d1b2e;display:flex;min-height:90vh;align-items:center;justify-content:center;text-align:center;padding:24px}"
        + "a{display:inline-block;margin-top:18px;background:#7b5cff;color:#fff;padding:14px 26px;border-radius:14px;text-decoration:none;font-weight:600}</style></head>"
        + "<body><div><h2>Sin conexión</h2><p>Revisa tu internet y vuelve a intentarlo.</p><a href='" + volver + "'>Reintentar</a></div></body></html>";
    v.loadDataWithBaseURL(BASE + "/", html, "text/html", "utf-8", null);
  }

  class Cliente extends WebViewClient {
    @Override public boolean shouldOverrideUrlLoading(WebView v, String url) { return decidir(url); }
    // Android 7 y más nuevos usan esta versión.
    public boolean shouldOverrideUrlLoading(WebView v, WebResourceRequest r) { return decidir(r.getUrl().toString()); }
    @Override public void onPageFinished(WebView v, String url) { recordar(url); }
    @Override public void onReceivedError(WebView v, WebResourceRequest r, WebResourceError e) { if (r.isForMainFrame()) errorDeRed(v); }
    @Override public void onReceivedError(WebView v, int codigo, String descripcion, String url) { if (Build.VERSION.SDK_INT < 23) errorDeRed(v); }
  }

  class Cromo extends WebChromeClient {
    @Override public void onProgressChanged(WebView v, int p) { barra.setProgress(p); barra.setVisibility(p < 100 ? View.VISIBLE : View.GONE); }
    @Override public boolean onShowFileChooser(WebView v, ValueCallback<Uri[]> cb, FileChooserParams params) {
      if (archivos != null) archivos.onReceiveValue(null);
      archivos = cb;
      try { startActivityForResult(params.createIntent(), ELEGIR_ARCHIVO); }
      catch (ActivityNotFoundException e) { archivos = null; return false; }
      return true;
    }
  }

  @Override protected void onActivityResult(int pedido, int resultado, Intent datos) {
    if (pedido == ELEGIR_ARCHIVO && archivos != null) {
      archivos.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultado, datos));
      archivos = null;
      return;
    }
    super.onActivityResult(pedido, resultado, datos);
  }

  // Descargas (cita para el calendario, cierre de caja en CSV): van a la carpeta Descargas con la sesión de la app.
  class Descargas implements DownloadListener {
    @Override public void onDownloadStart(String url, String agente, String disposicion, String tipo, long largo) {
      Uri u = Uri.parse(url);
      if (Build.VERSION.SDK_INT < 29 || !esNuestro(u)) { abrirFuera(u); return; }
      try {
        String nombre = URLUtil.guessFileName(url, disposicion, tipo);
        DownloadManager.Request r = new DownloadManager.Request(u);
        r.setMimeType(tipo);
        r.addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url));
        r.addRequestHeader("User-Agent", agente);
        r.setTitle(nombre);
        r.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
        r.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, nombre);
        ((DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE)).enqueue(r);
        Toast.makeText(Principal.this, "Descargando " + nombre + "… Tócalo en las notificaciones para abrirlo.", Toast.LENGTH_LONG).show();
      } catch (Exception e) {
        abrirFuera(u);
      }
    }
  }
}
