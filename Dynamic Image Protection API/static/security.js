document.addEventListener("DOMContentLoaded", function () {

    // 1️⃣ Improved Right-Click & Multi-Finger Gesture Prevention
    function blockRightClick(event) {
        event.preventDefault();
        const existing = document.getElementById("block-overlay");
        if (!existing) {
            const overlay = document.createElement("div");
            overlay.id = "block-overlay";
            overlay.style = "position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.8);color:white;font-size:24px;display:flex;align-items:center;justify-content:center;z-index:99999";
            overlay.innerText = "Right-click & long press are disabled!";
            document.body.appendChild(overlay);
            setTimeout(() => document.body.removeChild(overlay), 1500);
        }
    }

    document.addEventListener("contextmenu", blockRightClick);
    document.addEventListener("touchstart", function (event) {
        if (event.touches.length > 1) {
            blockRightClick(event);
        }
    }, { passive: false });

    // 2️⃣ Improved Copy-Paste & Text Selection Blocking
    function showOverlayMessage(message) {
        const overlay = document.createElement("div");
        overlay.style = "position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.8);color:white;display:flex;align-items:center;justify-content:center;font-size:20px;z-index:99999";
        overlay.textContent = message;
        document.body.appendChild(overlay);
        setTimeout(() => document.body.removeChild(overlay), 1500);
    }

    document.addEventListener("copy", (e) => {
        e.preventDefault();
        showOverlayMessage("Copying is disabled.");
    });

    document.addEventListener("paste", (e) => {
        e.preventDefault();
        showOverlayMessage("Pasting is disabled.");
    });

    document.addEventListener("touchend", (e) => {
        if (window.getSelection().toString().length > 0) {
            e.preventDefault();
            showOverlayMessage("Text selection is disabled.");
        }
    });

    // 3️⃣ Enhanced DevTools Detection
    function detectDevTools() {
        const threshold = 160;
        const before = new Date().getTime();
        debugger;
        const after = new Date().getTime();
        if (after - before > threshold) {
            document.body.innerHTML = "<h1 style='text-align:center;margin-top:30vh;color:white;background:black;height:100vh;'>DevTools Detected. Content Hidden.</h1>";
        }
    }

    setInterval(detectDevTools, 1000);

    // 4️⃣ Block Screenshot Attempts
    document.addEventListener("keyup", function (event) {
        if (event.key === "PrintScreen") {
            let overlay = document.createElement("div");
            overlay.style = "position:fixed;top:0;left:0;width:100vw;height:100vh;background:black;z-index:99999";
            document.body.appendChild(overlay);

            setTimeout(() => document.body.removeChild(overlay), 1500);

            navigator.clipboard.writeText("").catch(err => console.log("Clipboard clear failed:", err));
            alert("Screenshots are temporarily disabled.");
        }
    });

    // 5️⃣ Detect if App Goes to Background (Blur Content)
    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            document.body.style.filter = "blur(10px)";
        } else {
            document.body.style.filter = "none";
        }
    });

    // 🔒 Rate Limiting Script (Appended Non-intrusively)
    const requestTimes = [];
    const MAX_ACTIONS = 10;
    const TIME_FRAME = 60000;

    function sendLog() {
        fetch('/log-rate-limit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: "User exceeded UI rate limit" })
        }).catch(err => console.error("Error logging rate limit:", err));
    }

    function trackUserActions() {
        const now = Date.now();

        while (requestTimes.length > 0 && now - requestTimes[0] > TIME_FRAME) {
            requestTimes.shift();
        }

        requestTimes.push(now);

        if (requestTimes.length > MAX_ACTIONS) {
            alert("Too many actions! Please slow down.");
            sendLog();
            return false;
        }

        return true;
    }

    document.addEventListener("click", function(event) {
        if (!trackUserActions()) {
            event.preventDefault();
            console.warn("User exceeded UI rate limit.");
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleDashboardBtn');
    const dashboard = document.getElementById('securityDashboard');

    toggleBtn.addEventListener('click', function () {
        const isVisible = dashboard.style.display === 'block';
        dashboard.style.display = isVisible ? 'none' : 'block';

        if (!isVisible) {
            dashboard.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
