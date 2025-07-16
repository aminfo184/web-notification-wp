document.addEventListener('DOMContentLoaded', function() {
    console.log('WNW Debug: Script loaded and DOM is ready.');

    if (typeof wnw_data === 'undefined') {
        console.error('WNW Debug: FATAL - wnw_data object not found. The script cannot continue.');
        return;
    }
    console.log('WNW Debug: wnw_data object found successfully.', wnw_data);

    const subscribeButton = document.getElementById('wnw-subscribe-button');
    if (!subscribeButton) {
        console.error('WNW Debug: FATAL - Subscribe button with id "wnw-subscribe-button" not found in the page.');
        return;
    }
    console.log('WNW Debug: Subscribe button found successfully.');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('WNW Debug: Push messaging is not supported in this browser.');
        subscribeButton.disabled = true;
        return;
    }
    
    subscribeButton.disabled = false;
    console.log('WNW Debug: Push is supported. Button is enabled.');
    
    // منتظر کلیک کاربر می‌ماند
    subscribeButton.addEventListener('click', () => {
        console.log('WNW Debug: Subscribe button CLICKED!');
        subscribeUser();
    });
    console.log('WNW Debug: Click listener attached to the subscribe button.');

    function subscribeUser(){
        console.log('WNW Debug: subscribeUser() function called.');
        navigator.serviceWorker.ready.then(swReg => {
            console.log('WNW Debug: Service Worker is ready. Registration object:', swReg);
            
            // بررسی می‌کنیم که کنترلر فعال، Service Worker ما باشد
            if (swReg.active && swReg.active.scriptURL.includes('service-worker.js')) {
                console.log('WNW Debug: Our Service Worker is active. Proceeding to get subscription.');
                swReg.pushManager.getSubscription().then(sub => {
                    if(sub){
                        console.log('WNW Debug: Existing subscription found. Unsubscribing first...');
                        sub.unsubscribe().then(() => {
                            console.log('WNW Debug: Unsubscribed successfully. Proceeding with new subscription.');
                            proceedWithSubscription(swReg);
                        });
                    } else {
                        console.log('WNW Debug: No existing subscription. Proceeding with new subscription.');
                        proceedWithSubscription(swReg);
                    }
                });
            } else {
                console.error("WNW Debug: Could not get our Service Worker's registration. It may be controlled by another plugin or not active yet.");
                alert("خطا در آماده‌سازی سرویس نوتیفیکیشن. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.");
            }
        });
    }

    function proceedWithSubscription(swReg){
        console.log('WNW Debug: proceedWithSubscription() called.');
        if (!wnw_data.public_key) {
            console.error('WNW Debug: VAPID public key is missing.');
            alert('خطای پیکربندی: کلید عمومی نوتیفیکیشن تنظیم نشده است.');
            return;
        }

        const appKey = urlBase64ToUint8Array(wnw_data.public_key);
        swReg.pushManager.subscribe({userVisibleOnly:true, applicationServerKey:appKey})
            .then(sub => {
                console.log('WNW Debug: Browser subscription successful. Sending to server...');
                sendSubscriptionToServer(sub);
            })
            .catch(err => {
                console.error('WNW Debug: Failed to subscribe the user:', err);
                alert('خطا در فعال‌سازی نوتیفیکیشن. لطفاً مطمئن شوید اجازه نمایش نوتیفیکیشن را به سایت داده‌اید.');
            });
    }

    function sendSubscriptionToServer(sub){
        console.log('WNW Debug: sendSubscriptionToServer() called. Sending subscription to WordPress.');
        const formData = new FormData();
        formData.append('action', 'wnw_save_subscription');
        formData.append('nonce', wnw_data.nonce);
        formData.append('subscription', JSON.stringify(sub));

        fetch(wnw_data.ajax_url, {method:'POST', body:formData})
            .then(res => {
                if (!res.ok) throw new Error(`Network response was not ok, status: ${res.status}`);
                return res.json();
            })
            .then(data => {
                if(data.success) {
                    console.log('WNW Debug: Server successfully saved the subscription.');
                    alert('نوتیفیکیشن با موفقیت فعال شد!');
                } else {
                     console.error('WNW Debug: Subscription save failed on server.', data.data);
                }
            })
            .catch(err => console.error('WNW Debug: Fetch error while saving subscription.', err));
    }

    function urlBase64ToUint8Array(base64String){const padding='='.repeat((4-base64String.length%4)%4);const base64=(base64String+padding).replace(/-/g,'+').replace(/_/g,'/');const rawData=window.atob(base64);const outputArray=new Uint8Array(rawData.length);for(let i=0;i<rawData.length;++i){outputArray[i]=rawData.charCodeAt(i);}return outputArray;}
});