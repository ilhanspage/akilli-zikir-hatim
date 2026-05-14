package com.ilhanbeluk.akillizikirhatim

import android.app.Activity
import com.android.billingclient.api.AcknowledgePurchaseParams
import com.android.billingclient.api.BillingClient
import com.android.billingclient.api.BillingClient.ProductType.INAPP
import com.android.billingclient.api.BillingClientStateListener
import com.android.billingclient.api.BillingFlowParams
import com.android.billingclient.api.BillingResult
import com.android.billingclient.api.ConsumeParams
import com.android.billingclient.api.ProductDetails
import com.android.billingclient.api.QueryProductDetailsParams
import com.android.billingclient.api.Purchase
import com.android.billingclient.api.PurchasesUpdatedListener
import com.android.billingclient.api.PendingPurchasesParams

class BillingManager(
    private val activity: Activity,
    private val callback: (status: String, productId: String, message: String) -> Unit
) : PurchasesUpdatedListener {

    private val productIds = listOf("support_25", "support_50", "support_100", "support_250")
    private val products = mutableMapOf<String, ProductDetails>()

    private val billingClient = BillingClient.newBuilder(activity)
        .setListener(this)
        .enablePendingPurchases(
            PendingPurchasesParams.newBuilder()
                .enableOneTimeProducts()
                .build()
        )
        .enableAutoServiceReconnection()
        .build()

    init {
        connect()
    }

    fun destroy() {
        if (billingClient.isReady) billingClient.endConnection()
    }

    private fun connect(onReady: (() -> Unit)? = null) {
        if (billingClient.isReady) {
            onReady?.invoke()
            return
        }
        billingClient.startConnection(object : BillingClientStateListener {
            override fun onBillingSetupFinished(result: BillingResult) {
                if (result.responseCode == BillingClient.BillingResponseCode.OK) {
                    queryProducts { onReady?.invoke() }
                } else {
                    callback("error", "", "Google Play destek sistemi hazır değil: ${result.debugMessage}")
                }
            }

            override fun onBillingServiceDisconnected() {
                callback("error", "", "Google Play bağlantısı koptu. Lütfen tekrar deneyin.")
            }
        })
    }

    private fun queryProducts(onDone: (() -> Unit)? = null) {
        val productList = productIds.map {
            QueryProductDetailsParams.Product.newBuilder()
                .setProductId(it)
                .setProductType(INAPP)
                .build()
        }
        val params = QueryProductDetailsParams.newBuilder()
            .setProductList(productList)
            .build()

        billingClient.queryProductDetailsAsync(params) { result, queryProductDetailsResult ->
            if (result.responseCode == BillingClient.BillingResponseCode.OK) {
                products.clear()
                queryProductDetailsResult.productDetailsList.forEach { productDetails ->
                    products[productDetails.productId] = productDetails
                }
                if (products.isEmpty() && queryProductDetailsResult.unfetchedProductList.isNotEmpty()) {
                    callback("error", "", "Google Play ürünleri alınamadı. Play Console ürün ID ve aktiflik durumunu kontrol edin.")
                }
            } else {
                callback("error", "", "Google Play ürün sorgusu başarısız: ${result.debugMessage}")
            }
            onDone?.invoke()
        }
    }

    fun purchase(productId: String) {
        if (productId !in productIds) {
            callback("error", productId, "Destek ürünü bulunamadı.")
            return
        }

        connect {
            val details = products[productId]
            if (details == null) {
                queryProducts {
                    launchPurchase(productId)
                }
            } else {
                launchPurchase(productId)
            }
        }
    }

    private fun launchPurchase(productId: String) {
        val details = products[productId]
        if (details == null) {
            callback("error", productId, "Google Play ürünleri henüz hazır değil. Play Console ürünlerini kontrol edin.")
            return
        }

        val productParams = BillingFlowParams.ProductDetailsParams.newBuilder()
            .setProductDetails(details)
            .build()

        val params = BillingFlowParams.newBuilder()
            .setProductDetailsParamsList(listOf(productParams))
            .build()

        val result = billingClient.launchBillingFlow(activity, params)
        if (result.responseCode != BillingClient.BillingResponseCode.OK) {
            callback("error", productId, "Satın alma ekranı açılamadı: ${result.debugMessage}")
        }
    }

    override fun onPurchasesUpdated(result: BillingResult, purchases: MutableList<Purchase>?) {
        when (result.responseCode) {
            BillingClient.BillingResponseCode.OK -> {
                purchases.orEmpty().forEach { handlePurchase(it) }
            }
            BillingClient.BillingResponseCode.USER_CANCELED -> {
                callback("cancel", "", "Destek işlemi iptal edildi.")
            }
            else -> {
                callback("error", "", "Destek işlemi tamamlanamadı: ${result.debugMessage}")
            }
        }
    }

    private fun handlePurchase(purchase: Purchase) {
        val productId = purchase.products.firstOrNull().orEmpty()

        if (purchase.purchaseState != Purchase.PurchaseState.PURCHASED) {
            callback("pending", productId, "Destek işlemi beklemede.")
            return
        }

        if (!purchase.isAcknowledged) {
            val ackParams = AcknowledgePurchaseParams.newBuilder()
                .setPurchaseToken(purchase.purchaseToken)
                .build()
            billingClient.acknowledgePurchase(ackParams) { ack ->
                if (ack.responseCode == BillingClient.BillingResponseCode.OK) {
                    consumeSupportPurchase(productId, purchase.purchaseToken)
                } else {
                    callback("error", productId, "Destek işlemi onaylanamadı: ${ack.debugMessage}")
                }
            }
        } else {
            consumeSupportPurchase(productId, purchase.purchaseToken)
        }
    }

    private fun consumeSupportPurchase(productId: String, token: String) {
        val params = ConsumeParams.newBuilder()
            .setPurchaseToken(token)
            .build()

        billingClient.consumeAsync(params) { result, _ ->
            if (result.responseCode == BillingClient.BillingResponseCode.OK) {
                callback("success", productId, "Desteğin için teşekkür ederiz. Allah razı olsun.")
            } else {
                callback("success", productId, "Desteğin için teşekkür ederiz.")
            }
        }
    }
}
