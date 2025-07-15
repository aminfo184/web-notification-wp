/**
 * Frontend script to handle user subscription process.
 */
document.addEventListener("DOMContentLoaded", function () {
  const subscribeButton = document.getElementById("wnw-subscribe-button");

  if (!subscribeButton) {
    return;
  }

  if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
    console.warn("WNW: Push messaging is not supported.");
    subscribeButton.disabled = true;
    return;
  }

  // FIX: Add a dynamic cache-busting query parameter to the service worker URL.
  const serviceWorkerUrl = `/service-worker.js?v=${
    wnw_data.version || new Date().getTime()
  }`;

  navigator.serviceWorker
    .register(serviceWorkerUrl)
    .then(function (swReg) {
      console.log("WNW: Service Worker is registered successfully.", swReg);
      subscribeButton.disabled = false;
    })
    .catch(function (error) {
      console.error("WNW: Service Worker registration failed:", error);
    });

  subscribeButton.addEventListener("click", function () {
    subscribeUser();
  });

  function subscribeUser() {
    const applicationServerKey = urlBase64ToUint8Array(wnw_data.public_key);

    navigator.serviceWorker.ready.then(function (swReg) {
      swReg.pushManager
        .subscribe({
          userVisibleOnly: true,
          applicationServerKey: applicationServerKey,
        })
        .then(function (subscription) {
          console.log("WNW: User is subscribed:", subscription);
          sendSubscriptionToServer(subscription);
        })
        .catch(function (err) {
          console.error("WNW: Failed to subscribe the user:", err);
          alert(
            "خطا در فعال‌سازی نوتیفیکیشن. لطفاً مطمئن شوید اجازه نمایش نوتیفیکیشن را به سایت داده‌اید."
          );
        });
    });
  }

  function sendSubscriptionToServer(subscription) {
    let guestToken = getCookie("wnw_guest_token");
    if (!guestToken && !wnw_data.is_user_logged_in) {
      guestToken = generateToken(32);
      setCookie("wnw_guest_token", guestToken, 365);
    }

    const formData = new FormData();
    formData.append("action", "wnw_save_subscription");
    formData.append("nonce", wnw_data.nonce);
    formData.append("subscription", JSON.stringify(subscription));
    if (guestToken) {
      formData.append("guest_token", guestToken);
    }

    fetch(wnw_data.ajax_url, {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert("نوتیفیکیشن با موفقیت فعال شد!");
        } else {
          console.error("WNW: Failed to save subscription.", data.data.message);
        }
      })
      .catch((error) => {
        console.error("WNW: Error sending subscription to server.", error);
      });
  }

  // --- Helper Functions ---
  function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, "+")
      .replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
  }

  function setCookie(name, value, days) {
    let expires = "";
    if (days) {
      const date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = "; expires=" + date.toUTCString();
    }
    document.cookie = `${name}=${value || ""}${expires}; path=/`;
  }

  function generateToken(length) {
    const a =
      "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890".split(
        ""
      );
    const b = [];
    for (let i = 0; i < length; i++) {
      let j = (Math.random() * (a.length - 1)).toFixed(0);
      b[i] = a[j];
    }
    return b.join("");
  }
});
