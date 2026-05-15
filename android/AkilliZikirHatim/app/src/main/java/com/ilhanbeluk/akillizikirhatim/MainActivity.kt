package com.ilhanbeluk.akillizikirhatim

import android.annotation.SuppressLint
import android.app.Activity
import android.os.Bundle
import android.webkit.JavascriptInterface
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient

class MainActivity : Activity() {
    private lateinit var webView: WebView
    private lateinit var billingManager: BillingManager

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        webView = WebView(this)
        setContentView(webView)

        billingManager = BillingManager(this) { status, productId, message ->
            sendBillingResult(status, productId, message)
        }

        webView.webViewClient = WebViewClient()
        webView.webChromeClient = WebChromeClient()

        webView.settings.javaScriptEnabled = true
        webView.settings.domStorageEnabled = true
        webView.settings.databaseEnabled = true
        webView.settings.cacheMode = WebSettings.LOAD_DEFAULT
        webView.settings.mediaPlaybackRequiresUserGesture = false
        webView.settings.loadsImagesAutomatically = true
        webView.settings.useWideViewPort = true
        webView.settings.loadWithOverviewMode = true

        webView.addJavascriptInterface(BillingBridge(), "AkilliZikirBilling")
        webView.loadUrl(BuildConfig.WEB_URL)
    }

    override fun onBackPressed() {
        if (::webView.isInitialized && webView.canGoBack()) {
            webView.goBack()
            return
        }
        super.onBackPressed()
    }

    override fun onDestroy() {
        if (::billingManager.isInitialized) {
            billingManager.destroy()
        }
        if (::webView.isInitialized) {
            webView.removeJavascriptInterface("AkilliZikirBilling")
            webView.destroy()
        }
        super.onDestroy()
    }

    private fun sendBillingResult(status: String, productId: String, message: String) {
        val safeStatus = status.jsEscape()
        val safeProduct = productId.jsEscape()
        val safeMessage = message.jsEscape()
        runOnUiThread {
            webView.evaluateJavascript(
                "window.azhBillingResult && window.azhBillingResult('$safeStatus', '$safeProduct', '$safeMessage');",
                null
            )
        }
    }

    inner class BillingBridge {
        @JavascriptInterface
        fun purchase(productId: String) {
            runOnUiThread {
                billingManager.purchase(productId)
            }
        }

        @JavascriptInterface
        fun isAvailable(): Boolean = true
    }
}

private fun String.jsEscape(): String {
    return this
        .replace("\\", "\\\\")
        .replace("'", "\\'")
        .replace("\n", " ")
        .replace("\r", " ")
}
