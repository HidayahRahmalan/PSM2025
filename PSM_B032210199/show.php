<?php
session_start();
include 'activityTracker.php'; 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SheetJS for XLSX export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- jsPDF and autotable for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- jsPDF AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            margin: 20px;
        }
        .container {
            display: flex;
            width: 100%;
            gap: 20px;
        }
        .left-panel, .right-panel {
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .left-panel {
            width: 75%;
            background: #f9f9f9;
            overflow-x: auto; /* Enable horizontal scrolling */
            white-space: nowrap; /* Prevent text from wrapping */
            padding-bottom: 10px;
        }
        .right-panel {
            width: 25%;
            background: #fff;
        }
        .right-panel-bottom-btns {
            position: absolute;
            bottom: 20px;
            right: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .export-btn, .back-btn {
            font-size: 13px;
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .export-btn {
            background: #ff9800;
            color: #fff;
        }
        .export-btn:hover {
            background: #e68900;
        }
        .back-btn {
            background: #aaa;   /* Lighter grey */
            color: #fff;
        }
        .back-btn:hover {
            background: #888;   /* Slightly deeper on hover */
        }
        .table-container {
            overflow-x: auto; /* Scrollable table */
            max-width: 100%;
        }
        table {
            width: max-content; /* Ensure it takes only needed width */
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            white-space: nowrap; /* Prevent content from wrapping */
        }
        th {
            background-color:rgba(255, 153, 0, 0.58);
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .error {
            background-color: #ffebeb;
            font-weight: bold;
        }
        .highlight {
            background-color:rgba(255, 153, 0, 0.25) !important;
        }
        .duplicate-summary {
            cursor: pointer;
            color: black;
            padding: 10px;
            border-radius: 5px;
            text-align: left;
            font-weight: bold;
        }
        .duplicate-summary:hover {
            background-color: rgba(139, 139, 139, 0.33);
        }
        .duplicate-section {
            display: none;
            margin-top: 10px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background:rgba(245, 245, 245, 0.49);
        }
        .duplicate-type {
            cursor: pointer;
            margin: 5px 0;
        }
        .duplicate-value {
            cursor: pointer;
            color:rgb(255, 153, 0);
            margin-left: 3px;
        }
        .duplicate-value:hover {
            text-decoration: underline;
            color:rgb(243, 146, 0); 
        }
        .duplicate-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-left: 38px;
            padding: 5px 0;
        }
        .resolve-btn {
            font-size: 12px;
            padding: 8px 5px;
            background: #ff9800; 
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .resolve-btn:hover {
            background: rgba(139, 139, 139, 0.33);
        }
        .hidden-column {
            display: none;
        }
        .missingValue-summary {
            cursor: pointer;
            color: black;
            padding: 10px;
            border-radius: 5px;
            text-align: left;
            font-weight: bold;
            margin-top: 25px;
        }
        .missingValue-summary:hover {
            background-color: rgba(139, 139, 139, 0.33);
        }
        .missingValue-section {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background:rgba(245, 245, 245, 0.49);
        }
        .missing-value {
            cursor: pointer; /* Makes the cursor a hand (clickable) */
            color:rgb(255, 153, 0);
            text-decoration: underline; /* Underline to look like a hyperlink */
            margin-bottom: 10px;
        }

        .missing-value:hover {
            color:rgb(243, 146, 0); 
        }
        .resolveMV-btn {
            font-size: 12px;
            padding: 8px 5px;
            background: #ff9800; 
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .resolveMV-btn:hover {
            background: rgba(139, 139, 139, 0.33);
        }

        .inaccuraciesValue-summary {
            cursor: pointer;
            color: black;
            padding: 10px;
            border-radius: 5px;
            text-align: left;
            font-weight: bold;
            margin-top: 25px;
        }
        .inaccuraciesValue-summary:hover {
            background-color: rgba(139, 139, 139, 0.33);
        }
        .inaccuraciesValue-section {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background:rgba(245, 245, 245, 0.49);
        }
        .inaccuracies-value {
            cursor: pointer; /* Makes the cursor a hand (clickable) */
            color:rgb(255, 153, 0);
            text-decoration: underline; /* Underline to look like a hyperlink */
            margin-bottom: 10px;
        }

        .inaccuracies-value:hover {
            color:rgb(243, 146, 0); 
        }
        .resolveInaccuracies-btn {
            font-size: 12px;
            padding: 8px 5px;
            background: #ff9800; 
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }

        .resolveInaccuracies-btn:hover {
            background: rgba(139, 139, 139, 0.33);
        }

        .resolve-modal-table {
            max-height: 70vh;
            overflow-y: auto;
            margin: 10px 0;
            background-color: #f9f9f9;
            border-radius: 10px; /* Add rounded corners */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); /* Add shadow */
            padding: 15px; /* Add some padding */
            border: 1px solid #e0e0e0; /* Optional border */
        }

        .resolve-modal-table table {
            width: 100%;
            border-collapse: separate; /* Needed for rounded corners */
            border-spacing: 0;
            border-radius: 8px; /* Rounded corners for table */
            overflow: hidden; /* Ensures rounded corners work */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* Subtle inner shadow */
        }

        .resolve-modal-table th, 
        .resolve-modal-table td {
            padding: 8px;
            border: 1px solid black;
        }

        .resolve-modal-table th {
            background-color: #ff9800; /* Orange header */
            color: white;
            position: sticky;
            top: 0;
        }

        /* Normal rows */
        .resolve-modal-table tr {
            background-color: #fff; /* White background for normal rows */
            transition: all 0.5s ease;
        }

        /* Duplicate rows that will be removed */
        .resolve-modal-table tr.duplicate-row {
            background-color: rgba(255, 153, 0, 0.2); /* Light orange highlight */
        }

        /* When duplicate row is being processed */
        .resolve-modal-table tr.duplicate-row.removing {
            background-color: rgba(255, 0, 0, 0.3) !important; /* Red when being removed */
            transform: scale(0.98);
        }

        /* Animation for removal */
        @keyframes fadeOutRow {
            0% { opacity: 1; height: auto; padding: inherit; margin: inherit; }
            100% { opacity: 0; height: 0; padding: 0; margin: 0; }
        }

        .resolve-modal-table tr.fade-out-row {
            animation: fadeOutRow 0.8s forwards;
            overflow: hidden;
        }

        /* Modal container styles */
        .resolve-modal-popup {
            border-radius: 12px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
            overflow: hidden;
        }

        /* Table header rounded corners */
        .resolve-modal-table table tr:first-child th:first-child {
            border-top-left-radius: 8px;
        }
        .resolve-modal-table table tr:first-child th:last-child {
            border-top-right-radius: 8px;
        }

        /* Table footer rounded corners */
        .resolve-modal-table table tr:last-child td:first-child {
            border-bottom-left-radius: 8px;
        }
        .resolve-modal-table table tr:last-child td:last-child {
            border-bottom-right-radius: 8px;
        }

    </style>
</head>
<body>

<div class="container">
    <!-- Left Panel: Extracted Data (Scrollable) -->
    <div class="left-panel">
        <h2>Extracted Data</h2>
        <div class="table-container"></div> <!-- JavaScript will populate this -->
    </div>

    <!-- Right Panel: Duplicate Records -->
    <div class="right-panel">
        <div class="duplicate-summary">
            <span id="duplicate-count">0</span> Duplicate Records
        </div>
        <div class="duplicate-section" id="duplicate-container"></div>
        <button class="resolve-btn" onclick="resolveDuplicates()">Resolve</button>


        <div class="inaccuraciesValue-summary">
            <span id="inaccuracies-count">0</span> Inaccuracies Values
        </div>
        <div class="inaccuraciesValue-section" id="inaccuraciesValue-container"></div>
        <button class="resolveInaccuracies-btn" onclick="resolveInaccuracies()">Resolve</button>


        <div class="missingValue-summary">
            <span id="missing-count">0</span> Missing Values
        </div>
        <div class="missingValue-section" id="missingValue-container"></div>
        <button class="resolveMV-btn" onclick="resolveMissingValue()">Resolve</button>

        <div class="right-panel-bottom-btns">
            <button class="export-btn" onclick="openExportDialog()">Export</button>
            <button class="back-btn" onclick="window.location.href='userMainPage.php'">Back to Home</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    fetchData(); // Load data on page load

    // Toggle duplicate section
    $(".duplicate-summary").click(function () {
        $(".duplicate-section").slideToggle();
    });

    // Expand/Collapse duplicate categories
    $(document).on("click", ".duplicate-type", function () {
        $(this).next(".duplicate-values").slideToggle();
        $(this).text($(this).text().startsWith("▶") ? "▼ " + $(this).text().slice(2) : "▶ " + $(this).text().slice(2));
    });

    // Highlight duplicate rows
    $(document).on("click", ".duplicate-value", function () {
        let rowNumber = $(this).data("row");
        $(".data-row").removeClass("highlight");
        $(".data-row[data-row='" + rowNumber + "']").addClass("highlight");
    });

    $(".missingValue-summary").click(function () {
        $(".missingValue-section").slideToggle();
    });

    $(".inaccuraciesValue-summary").click(function () {
        $(".inaccuraciesValue-section").slideToggle();
    });

});

let dataLoaded = false;


// Fetch Data from `getSessionData.php`
function fetchData() {
    $.ajax({
        url: "getSessionData.php",
        type: "GET",
        dataType: "json",
        success: function (response) {

            console.log("✅ Raw Extracted Data (fetchData, show.php):", response);

            sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(response.extractedData));

            // 🛠️ Fix data structure before displaying
            let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");


            if (Array.isArray(extractedData) && extractedData.length > 0 && !extractedData[0].hasOwnProperty("row")) {
                extractedData = extractedData.map((data, index) => ({
                    row: index + 1, // Assign row numbers
                    data: data      // Keep the original array inside "data"
                }));

                sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(extractedData));
            }

            sessionStorage.setItem("headers", JSON.stringify(response.headers));
            
            // ✅ Infer report type from headers
            const headersLower = response.headers.map(h => h.toLowerCase());
            let reportType = "unknown";
            if (headersLower.includes("transaction date")) {
                reportType = "payout";
            } else if (headersLower.includes("return request date")) {
                reportType = "refund";
            }
            sessionStorage.setItem("report_type", reportType);
            console.log("📎 Inferred report type:", reportType);
            
            sessionStorage.setItem("duplicateData", JSON.stringify(response.duplicateData)); //not yet assign row number 
            sessionStorage.setItem("missing_values", JSON.stringify(response.missing_values));
            console.log("📌 [Debug] Saving original_missing_values (fetchdata):", sessionStorage.getItem("missing_values"));
            sessionStorage.setItem("original_missing_values", sessionStorage.getItem("missing_values"));
            sessionStorage.setItem("inaccuracies", JSON.stringify(response.inaccuracies));
            
            // Set dataLoaded to true BEFORE calling display functions
            dataLoaded = true;

            displayExtractedData();
            displayDuplicateData();
            displayMissingValues();
            displayInaccuracies();
        },
        error: function () {
            console.error("Failed to fetch data.");
        }
    });
}




function checkAllProblemsResolvedAndPromptExport() {
    if (!dataLoaded) return;

    // Get data directly from sessionStorage
    const duplicateData = JSON.parse(sessionStorage.getItem("duplicateData") || "{}");
    const missingValues = JSON.parse(sessionStorage.getItem("missing_values") || "[]");
    const inaccuracies = JSON.parse(sessionStorage.getItem("inaccuracies") || "{}");

    // Check if there are any duplicates
    const hasDuplicates = Object.values(duplicateData).some(arr => Array.isArray(arr) && arr.length > 0);

    // Check if there are any missing values
    const hasMissing = Array.isArray(missingValues) && missingValues.length > 0;

    // Check if there are any inaccuracies
    const hasInaccuracies = Object.values(inaccuracies).some(arr => Array.isArray(arr) && arr.length > 0);

    if (!hasDuplicates && !hasMissing && !hasInaccuracies) {
        let reportType = sessionStorage.getItem("report_type") || "cleaned_data";
        let filename = reportType === "payout" ? "Cleaned_Payout.xlsx"
                    : reportType === "refund" ? "Cleaned_Refund.xlsx"
                    : "cleaned_data.xlsx";
        showExportDialog(filename);
    }
}


function showExportDialog(filename) {
    Swal.fire({
        title: "Export Cleaned Data",
        html: `
            <div style="margin-bottom:10px;">
                <b>File Name:</b> <span style="color:#ff9800">${filename}</span>
            </div>
            <div style="margin-bottom:10px;">
                <b>Choose Export Format:</b>
                <select id="export-format" style="margin-left:10px;padding:4px;">
                    <option value="csv">CSV</option>
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Export",
        cancelButtonText: "Close",
        confirmButtonColor: "#ff9800",
        cancelButtonColor: "#aaa",
        //background: localStorage.getItem("theme") === "dark" ? "#333" : "#fff",
        //color: localStorage.getItem("theme") === "dark" ? "#fff" : "#000",
        background: "#fff",
        color: "#000",
        width: "400px",
        padding: "16px",
        customClass: {
            popup: "custom-alert"
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const format = document.getElementById("export-format").value;
            exportExtractedData(filename, format);
        }
    });
}


function openExportDialog() {
    // Check if all problems are resolved
    const duplicateData = JSON.parse(sessionStorage.getItem("duplicateData") || "{}");
    const missingValues = JSON.parse(sessionStorage.getItem("missing_values") || "[]");
    const inaccuracies = JSON.parse(sessionStorage.getItem("inaccuracies") || "{}");

    const hasDuplicates = Object.values(duplicateData).some(arr => Array.isArray(arr) && arr.length > 0);
    const hasMissing = Array.isArray(missingValues) && missingValues.length > 0;
    const hasInaccuracies = Object.values(inaccuracies).some(arr => Array.isArray(arr) && arr.length > 0);

    if (hasDuplicates || hasMissing || hasInaccuracies) {
        Swal.fire({
            icon: "error",
            title: "Cannot Export",
            text: "You must resolve all duplicates, missing values, and inaccuracies before exporting.",
            confirmButtonColor: "#ff9800"
        });
        return;
    }

    // All problems resolved, proceed to export dialog
    let reportType = sessionStorage.getItem("report_type") || "cleaned_data";
    let filename = reportType === "payout" ? "Cleaned_Payout.xlsx"
                : reportType === "refund" ? "Cleaned_Refund.xlsx"
                : "cleaned_data.xlsx";
    showExportDialog(filename);
}


function saveCleanDataToDB(cleanedData, headers, reportType, datasetID, callback) {
    let actionDetails = JSON.parse(sessionStorage.getItem("actionDetails") || "[]");
    console.log("🔎 actionDetails in saveCleanDataToDB:", actionDetails);

    $.ajax({
        url: "saveCleanData.php",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
            data: cleanedData,
            originalData: JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]"),
            actions: JSON.parse(sessionStorage.getItem("actionDetails") || "[]"),
            headers: headers,
            reportType: reportType,
            datasetID: datasetID 
        }),
        success: function(response) {
            if (response.success) {
                sessionStorage.setItem("cleanDataSaved", "true"); // ✅ Set flag here
            }

            if (callback) callback(response);
        },
        error: function(xhr) {
            console.error("AJAX Error:", xhr.status, xhr.responseText);
            Swal.fire("Error", "Failed to save clean data to database.\n" + xhr.responseText, "error");
        }
    });
}


function logExportToDB(eformat, datasetID, callback) {
    $.ajax({
        url: "logExport.php",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
            eformat: eformat,
            datasetid: datasetID
        }),
        success: function(response) {
            if (callback) callback(response);
        },
        error: function(xhr) {
            Swal.fire("Error", "Failed to log export.", "error");
        }
    });
}


function exportExtractedData(filename, format) {
    const extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    const headers = JSON.parse(sessionStorage.getItem("headers") || "[]");
    const reportType = sessionStorage.getItem("report_type") || "cleaned_data";
    const datasetID = sessionStorage.getItem("datasetID"); 

    if (!extractedData.length || !headers.length) {
        showAlert("error", "Export Failed", "No data to export.");
        return;
    }

    const cleanDataSaved = sessionStorage.getItem("cleanDataSaved") === "true";

    const proceedExport = () => {
        logExportToDB(format, datasetID, function(logResp) {
            const rows = [headers, ...extractedData.map(row => row.data)];

            if (format === "csv") {
                const csvContent = rows.map(row => 
                    row.map(cell => {
                        if (typeof cell === "string" && /^\d{4}-\d{2}-\d{2}$/.test(cell)) {
                            return `="${cell}"`;
                        }
                        return `"${String(cell).replace(/"/g, '""')}"`;
                    }).join(",")
                ).join("\n");
                const blob = new Blob([csvContent], { type: "text/csv" });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = filename.replace(/\.[^/.]+$/, "") + ".csv";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else if (format === "xlsx") {
                if (typeof XLSX === "undefined") {
                    showAlert("error", "Export Failed", "Excel export requires SheetJS library.");
                    return;
                }
                const ws = XLSX.utils.aoa_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
                XLSX.writeFile(wb, filename.replace(/\.[^/.]+$/, "") + ".xlsx");
            } else if (format === "pdf") {
                const jsPDF = window.jspdf.jsPDF;

                if (typeof jsPDF === "undefined") {
                    showAlert("error", "Export Failed", "jsPDF is not available.");
                    return;
                }

                const doc = new jsPDF();

                if (typeof doc.autoTable !== "function") {
                    showAlert("error", "Export Failed", "autoTable is not available.");
                    return;
                }

                doc.autoTable({
                    head: [headers],
                    body: extractedData.map(row => row.data),
                    theme: "grid",
                    styles: {
                        fontSize: 8,
                        cellPadding: 2,
                        overflow: "linebreak",
                    },
                    headStyles: {
                        fillColor: [255, 152, 0],
                        textColor: [255, 255, 255],
                        halign: "center",
                        fontStyle: "bold",
                    },
                    bodyStyles: {
                        fillColor: [255, 243, 224],
                        textColor: [0, 0, 0],
                    },
                    alternateRowStyles: {
                        fillColor: [255, 236, 179],
                    },
                    didDrawPage: function (data) {
                        doc.setFontSize(12);
                        doc.setTextColor(40);
                        doc.text("Cleaned Data Export", data.settings.margin.left, 10);
                    },
                    margin: { top: 20 }
                });

                doc.save(filename.replace(/\.[^/.]+$/, "") + ".pdf");
            }
        });
    };

    // Only save to DB once
    if (cleanDataSaved) {
        proceedExport();
    } else {
        saveCleanDataToDB(
            extractedData.map(row => row.data),
            headers,
            reportType,
            datasetID,
            function(response) {
                if (!response.success) {
                    showAlert("error", "Save Failed", response.message || "Failed to save clean data to DB.");
                    return;
                }
                proceedExport();
            }
        );
    }
}



function refreshAllDisplaysAndCheckExport() {
    displayExtractedData();
    displayDuplicateData();
    displayMissingValues();
    displayInaccuracies();
    checkAllProblemsResolvedAndPromptExport();
}

window.addEventListener("beforeunload", function () {
    sessionStorage.removeItem("cleanDataSaved");
});



// Display Extracted Data
function displayExtractedData() {
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || "[]");

    console.log("🔍 Extracted Data (displayExtractedData, show.php):", extractedData);

    if (!Array.isArray(extractedData) || extractedData.length === 0 || headers.length === 0) {
        console.warn("No extracted data available.");
        $(".table-container").html("<p>No extracted data found.</p>");
        return;
    }

    // Filter valid rows (row number can be number or array, and must have data)
    extractedData = extractedData.filter(row =>
        row &&
        (typeof row.row === "number" || Array.isArray(row.row)) &&
        Array.isArray(row.data)
    );

    if (extractedData.length === 0) {
        console.warn("⚠️ No valid extracted data after filtering.");
        $(".table-container").html("<p>No valid extracted data found.</p>");
        return;
    }

    sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(extractedData));
    sessionStorage.setItem("extractedData", JSON.stringify(extractedData)); // ✅ Sync both versions

    console.log("✅ Saved extractedData with row numbers: (displayExtractedData, show.php) ", extractedData);

    let orderIdToRow = {}; 

    extractedData.forEach((row, index) => {

        //let orderId = row.data[0];
        let orderId = String(row.data[0]).trim(); // Force string

        let rowNumber = row.row;   // ✅ Use actual row number from extractedData

        if (!orderIdToRow[orderId]) {
            orderIdToRow[orderId] = [];
        }
        orderIdToRow[orderId].push(rowNumber);
    });

    sessionStorage.setItem("orderIdToRow", JSON.stringify(orderIdToRow));

    if (extractedData.length > 0 && headers.length > 0) {
        //let tableHTML = "<table border='1'><tr><th>Row</th>";
        let tableHTML = "<table border='1'><tr><th class='hidden-column'>Row</th>";

        headers.forEach(header => {
            tableHTML += `<th>${header}</th>`;
        });
        tableHTML += "</tr>";

        extractedData.forEach((row, index) => {
    
            let rowNumber = row.row; // ✅ Use correct row number

            tableHTML += `<tr class='data-row' data-row='${rowNumber}'>`;
            tableHTML += `<td class='hidden-column'>${rowNumber}</td>`;

            row.data.forEach(cell => {
                tableHTML += `<td>${cell}</td>`;
            });

            tableHTML += "</tr>";
        });

        tableHTML += "</table>";
        $(".table-container").html(tableHTML);

    } else {
        $(".table-container").html("<p>No extracted data found.</p>");
    }
}



// Display Duplicate Data
function displayDuplicateData() {
    let duplicateData = JSON.parse(sessionStorage.getItem("duplicateData") || "{}"); // Ensure it's an object
    let duplicateContainer = $("#duplicate-container");
    let duplicateCount = $("#duplicate-count");
    let orderIdToRow = JSON.parse(sessionStorage.getItem("orderIdToRow") || "{}");
    console.log("📌 orderIdToRow Mapping:", orderIdToRow);
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");


    console.log("Duplicate Data (displayDuplicateData, show.php) :", duplicateData);

    duplicateContainer.empty(); // Clear container before inserting new duplicates

    /*if (Object.keys(duplicateData).length === 0) {
        duplicateCount.text("0");  // Update duplicate count to 0
        showAlert("info", "No Duplicates", "No duplicate records found.");
        return;  // Exit function early
    }*/
   if (Object.keys(duplicateData).length === 0) {
        duplicateCount.text("0");
        // Only show alert if not all problems are resolved
        if (!(Number($("#missing-count").text()) === 0 && Number($("#inaccuracies-count").text()) === 0)) {
            showAlert("info", "No Duplicates", "No duplicate records found.");
        }
        checkAllProblemsResolvedAndPromptExport();
        return;
    }

    let uniqueRowNumbers = new Set(); // Store only unique row numbers
    let displayedOrderIDs = new Set(); // Track which Order IDs have been displayed in any category
    let duplicateHTML = "";

    // Define priority order for duplicate categories
    let categoryPriority = ["fully_identical_rows", "same_order_id_diff_attributes", "same_attributes_diff_order_id"];


    categoryPriority.forEach(category => {
        let records = duplicateData[category] || [];

        if (records.length > 0) {
            let categoryDisplayName = {
                "same_attributes_diff_order_id": "Similar Items, Different Order ID",
                "same_order_id_diff_attributes": "Same Order ID, Different Details",
                "fully_identical_rows": "Fully Identical Rows"
            }[category];

            let categoryHTML = `<div class="duplicate-category"><strong>${categoryDisplayName}</strong></div>`;
            let hasValidEntries = false; // Track if this category actually contains new duplicates

            let processedOrderIDs = new Set(); // Prevent duplicate Order IDs within a category

            records.forEach(dup => {
                //let orderID = dup["Order ID"];
                //let orderID = String(dup["Order ID"]);  // Force string
                let orderID = String(dup["Order ID"]).trim();

                if (displayedOrderIDs.has(orderID)) return; // Skip if already displayed in a higher-priority category

                let rowNumbers = orderIdToRow[orderID] || [];

                if (!Array.isArray(rowNumbers)) {
                    rowNumbers = [rowNumbers]; // Convert to array if needed
                }

                let filteredRowNumbers = rowNumbers.filter(rowNum => !uniqueRowNumbers.has(rowNum)); // Exclude already counted rows

                if (filteredRowNumbers.length > 0) {
                    hasValidEntries = true;
                    displayedOrderIDs.add(orderID); // Mark this Order ID as already displayed
                    filteredRowNumbers.forEach(rowNum => uniqueRowNumbers.add(rowNum)); // Add row numbers to avoid duplicates

                    categoryHTML += `<div class="duplicate-type" data-type="${orderID}">▶ Order ID: ${orderID} (${filteredRowNumbers.length})</div>`;
                    categoryHTML += `<div class="duplicate-values" style="display: none;">`;

                    filteredRowNumbers.forEach(rowNumber => {
                        categoryHTML += `
                            <div class="duplicate-item">
                                <div class="duplicate-value" data-row="${rowNumber}">
                                    (Row ${rowNumber})
                                </div>
                            </div>
                        `;
                    });

                    categoryHTML += `</div>`;
                }
            });

            if (hasValidEntries) {
                duplicateHTML += categoryHTML; // ✅ Only add categories that have new duplicates
            }
        }
    });


    // ✅ Update duplicate count using unique row numbers
    duplicateCount.text(uniqueRowNumbers.size);
    duplicateContainer.html(duplicateHTML || "<p>No duplicates found.</p>");


    // Ensure event listeners aren't duplicated
    $(".duplicate-type").off("click").on("click", function (event) {
        event.stopPropagation();
        let nextElement = $(this).next(".duplicate-values");
        if (!nextElement.is(":animated")) {
            nextElement.slideToggle();
        }
    });


    // ✅ Click event to highlight specific row
    $(".duplicate-value").on("click", function () {
        let rowNumber = $(this).attr("data-row");
        $("tr").removeClass("highlight");
        $(`tr[data-row="${rowNumber}"]`).addClass("highlight");
    });


    // ✅ Initialize storedDuplicates in the correct format
    let storedDuplicates = {
        "fully_identical_rows": [],
        "same_order_id_diff_attributes": [],
        "same_attributes_diff_order_id": []
    }; 


    Object.keys(duplicateData).forEach(category => {
        let records = duplicateData[category] || [];

        records.forEach(dup => {
            let orderID = dup["Order ID"];
            let rowNumbers = orderIdToRow[orderID] || []; // Get assigned row numbers

            // Ensure rowNumbers is an array
            if (!Array.isArray(rowNumbers)) {
                rowNumbers = [rowNumbers];
            }

            rowNumbers.forEach(rowNum => {
                let matchedOriginal;

                if (extractedData[0].data.length === 6) { // Refund Report (6 columns)
                    matchedOriginal = extractedData.find(row =>
                        row.row === rowNum &&
                        row.data[1] === dup["Product Name"] // Match Order ID and Product Name
                    );
                } else if (extractedData[0].data.length === 8) { // Payout Report (8 columns)
                    matchedOriginal = extractedData.find(row =>
                        row.row === rowNum &&
                        row.data[1] === dup["Transaction Date"] // Match Order ID and Transaction Date
                    );
                }

                if (matchedOriginal) {
                    if (!storedDuplicates[category].some(item => item.row === rowNum && (
                        (extractedData[0].data.length === 6 && item["Product Name"] === dup["Product Name"]) ||
                        (extractedData[0].data.length === 8 && item["Transaction Date"] === dup["Transaction Date"])
                    ))) {
                        storedDuplicates[category].push({
                            ...dup,
                            row: rowNum
                        });
                    }
                } else {
                    console.warn(`⚠️ Skipping unmatched row assignment for Order ID: ${orderID}, Row: ${rowNum}`);
                }
            });
        });
    });


    sessionStorage.setItem("storedDuplicateRows", JSON.stringify(storedDuplicates));
    console.log("Stored Duplicate Rows (displayDuplicateData, show.php) :", storedDuplicates);

}


function displayMissingValues() {
    let missingValues = JSON.parse(sessionStorage.getItem("missing_values") || "[]"); 
    let missingContainer = $("#missingValue-container");
    let missingCount = $("#missing-count");

    console.log("Missing Values (displayMissingValues, show.php):", missingValues);

    missingContainer.empty(); 

    /*if (missingValues.length === 0) {
        missingCount.text("0");  
        showAlert("info", "No Missing Values", "No missing values detected.");
        return;
    }*/
   if (missingValues.length === 0) {
        missingCount.text("0");
        // Only show alert if not all problems are resolved
        if (!(Number($("#duplicate-count").text()) === 0 && Number($("#inaccuracies-count").text()) === 0)) {
            showAlert("info", "No Missing Values", "No missing values detected.");
        }
        checkAllProblemsResolvedAndPromptExport();
        return;
    }

    let uniqueRows = new Set();
    let missingHTML = `<div class="missing-category"><strong></strong></div>`;

    missingValues.forEach(({ row, column, column_name }) => {
        uniqueRows.add(row);

        // 🛠️ Add proper `data-row` and `data-column`
        missingHTML += `
            <div class="missing-item" data-row="${row}">
                <div class="missing-value" data-row="${row}" data-column="${column}">
                    Row ${row}, Missing: ${column_name}
                </div>
            </div>`;
    });

    //missingCount.text(uniqueRows.size);
    missingCount.text(missingValues.length);
    missingContainer.html(missingHTML || "<p>No missing values found.</p>");

    // ✅ Use event delegation for dynamically created elements
    // ✅ Use event delegation for dynamically created elements
    $(document).off("click", ".missing-value").on("click", ".missing-value", function () {
        let rowNumber = $(this).attr("data-row");
        let columnIndex = parseInt($(this).attr("data-column")) + 1; // Convert to 1-based index

        console.log(`🔍 Highlighting row ${rowNumber}, column ${columnIndex}`);

        $("tr").removeClass("highlight");
        $("td").removeClass("highlight");

        let targetRow = $(`tr[data-row="${rowNumber}"]`);
        let targetCell = targetRow.find(`td:nth-child(${columnIndex})`);

        if (targetCell.length) {
            targetCell.addClass("highlight"); // Highlight the exact field

            // ✅ Scroll cell into view vertically and horizontally
            targetCell[0].scrollIntoView({
                behavior: "smooth",
                block: "center",
                inline: "center" // ensures horizontal scrolling
            });

            // 🔧 Optional: If scrollIntoView fails to align due to fixed table container, use manual scroll
            const scrollableContainer = $(".your-table-wrapper-selector"); // replace with your actual table wrapper selector
            if (scrollableContainer.length) {
                const container = scrollableContainer[0];
                const cellRect = targetCell[0].getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();

                const offsetLeft = cellRect.left - containerRect.left + container.scrollLeft - (container.clientWidth / 2) + (targetCell.outerWidth() / 2);

                $(container).animate({
                    scrollLeft: offsetLeft
                }, 500);
            }

        } else {
            console.warn(`⚠️ No matching cell found for Row ${rowNumber}, Column ${columnIndex}`);
        }
    });

}


function displayInaccuracies() {
    const inaccuraciesData = JSON.parse(sessionStorage.getItem("inaccuracies") || "{}");
    const inaccuraciesContainer = $("#inaccuraciesValue-container");
    const inaccuraciesCount = $("#inaccuracies-count");

    console.log("inaccuraciesData (displayInaccuracies, show.php):", inaccuraciesData);

    let allInaccuracies = [];

    // Process all categories directly as flat arrays
    ["incorrect_amount_calculation", "negative_values", "wrong_status_value", "misleading_timestamps"].forEach((category) => {
        const entries = inaccuraciesData[category] || [];
        if (Array.isArray(entries)) {
            entries.forEach(({ row, column, column_name, report_type, issue }) => {
                allInaccuracies.push({
                    row,
                    column,
                    column_name,
                    issue
                });
            });
        } else {
            console.warn(`Expected array for ${category}, got:`, entries);
        }
    });


    inaccuraciesContainer.empty();

    /*if (allInaccuracies.length === 0) {
        inaccuraciesCount.text("0");
        showAlert("info", "No Inaccuracy Values", "No inaccuracy values detected.");
        return;
    }*/
   if (allInaccuracies.length === 0) {
        inaccuraciesCount.text("0");
        // Only show alert if not all problems are resolved
        if (!(Number($("#duplicate-count").text()) === 0 && Number($("#missing-count").text()) === 0)) {
            showAlert("info", "No Inaccuracy Values", "No inaccuracy values detected.");
        }
        checkAllProblemsResolvedAndPromptExport();
        return;
    }

    let html = "";

    allInaccuracies.forEach(({ row, column, column_name, issue }) => {
        const colIndex = parseInt(column);
        if (isNaN(colIndex)) {
            console.warn(`⚠️ Invalid column index:`, { row, column, column_name });
            return;
        }

        const label = issue ? `${issue} (${column_name})` : column_name;

        html += `
            <div class="inaccuracies-item" data-row="${row}">
                <div class="inaccuracies-value" data-row="${row}" data-column="${colIndex}">
                    Row ${row} – ${label}
                </div>
            </div>`;
    });



    inaccuraciesCount.text(allInaccuracies.length);
    inaccuraciesContainer.html(html);

    // Highlight cell on click with auto-scroll
    $(document).off("click", ".inaccuracies-value").on("click", ".inaccuracies-value", function () {
        const row = $(this).attr("data-row");
        const col = parseInt($(this).attr("data-column")) + 1; // +1 if your display vs index offset

        $("tr").removeClass("highlight");
        $("td").removeClass("highlight");

        const targetRow = $(`tr[data-row="${row}"]`);
        const targetCell = targetRow.find(`td:nth-child(${col})`);

        if (targetCell.length) {
            targetCell.addClass("highlight");

            // Auto-scroll vertically to the row
            $('html, body').animate({
                scrollTop: targetCell.offset().top - 100 // Adjust -100 as needed for header offset
            }, 500);

            // If table has horizontal scroll (wrapped in .table-container), scroll horizontally to the cell
            const tableContainer = $(".table-container"); // adjust selector to your table wrapper
            if (tableContainer.length) {
                const containerOffset = tableContainer.offset().left;
                const cellOffset = targetCell.offset().left;
                const currentScroll = tableContainer.scrollLeft();
                const scrollTo = currentScroll + cellOffset - containerOffset - 50; // Adjust -50 as needed

                tableContainer.animate({
                    scrollLeft: scrollTo
                }, 500);
            }

        } else {
            console.warn(`⚠️ No matching cell found for Row ${row}, Column ${col}`);
        }
    });



}




// Resolve duplicates using AJAX
function resolveDuplicates() {
    let storedDuplicateRowsRaw = sessionStorage.getItem("storedDuplicateRows");
    let storedDuplicateRows = storedDuplicateRowsRaw ? JSON.parse(storedDuplicateRowsRaw) : {};
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || []);

    console.log("📌 Stored Duplicate Rows (resolveDuplicates, show.php) :", storedDuplicateRows);

    if (!storedDuplicateRows || Object.keys(storedDuplicateRows).length === 0) {
        Swal.fire("Error", "No stored duplicate data available.", "error");
        return;
    }

    // Create HTML table for the modal
    let tableHTML = '<div class="resolve-modal-table"><table><tr>';
    headers.forEach(header => {
        tableHTML += `<th>${header}</th>`;
    });
    tableHTML += '</tr>';
    
    extractedData.forEach(row => {
        tableHTML += `<tr id="row-${row.row}">`;
        row.data.forEach(cell => {
            tableHTML += `<td>${cell}</td>`;
        });
        tableHTML += '</tr>';
    });
    tableHTML += '</table></div>';

    // Show modal with table
    const resolveModal = Swal.fire({
        title: "Resolving Duplicates...",
        html: tableHTML,
        width: '90%',
        showConfirmButton: false,
        allowOutsideClick: false,
        customClass: {
            container: 'resolve-modal-container',
            popup: 'resolve-modal-popup'
        },
        didOpen: () => {
            // Highlight duplicate rows that will be removed
            Object.values(storedDuplicateRows.fully_identical_rows || []).forEach(row => {
                $(`#row-${row.row}`).addClass('duplicate-row');
            });
        }
    });

    // Process duplicates after modal is shown
    let allDuplicates = {}; 
    Object.keys(storedDuplicateRows).forEach(category => {
        allDuplicates[category] = storedDuplicateRows[category].map(row => {
            let formattedRow = {};
            Object.keys(row).forEach(key => {
                let newKey = key.toLowerCase().replace(/\s+/g, "_");
                formattedRow[newKey] = row[key];
            });
            return formattedRow;
        });
    });

    $.ajax({
        url: "resolveDuplicates.php",
        type: "POST",
        data: JSON.stringify(allDuplicates),
        contentType: "application/json",
        dataType: "json",
        timeout: 15000,
        success: function (response) {
            console.log("✅ FastAPI Response (resolveDuplicates, show.php):", response);

            // Inside the success callback of $.ajax in resolveDuplicates():

            if (response.error) {
                resolveModal.close();
                Swal.fire("Error", response.error, "error");
            } else {
                // Store resolved data first
                sessionStorage.setItem("resolvedData", JSON.stringify(response.resolved));
                
                // Get all rows to remove
                const resolvedItems = response.resolved.resolved || response.resolved;
                const fullyIdenticalResolutions = resolvedItems.fully_identical_rows || [];
                
                // Process removals one by one with delay
                let removalIndex = 0;
                const removalInterval = setInterval(() => {
                    if (removalIndex >= fullyIdenticalResolutions.length) {
                        clearInterval(removalInterval);
                        
                        // Close modal after all animations complete
                        setTimeout(() => {
                            resolveModal.close();
                            
                            // Apply resolutions after visual removal
                            applyResolutions(response.resolved);
                            
                            // Log actions
                            let actionDetails = JSON.parse(sessionStorage.getItem("actionDetails") || []);
                            console.log("🔎 actionDetails rd (b4):", actionDetails);

                            Object.values(resolvedItems).forEach(resolutionArray => {
                                if (Array.isArray(resolutionArray)) {
                                    resolutionArray.forEach(resolution => {
                                        if (resolution.action === "remove") {
                                            actionDetails.push({
                                                type: "Remove Duplicate",
                                                detail: `Removed duplicate row ${resolution.row}`,
                                                action: "remove",
                                                row: resolution.row,
                                                timestamp: new Date().toISOString()
                                            });
                                        } else if (resolution.action === "merge") {
                                            actionDetails.push({
                                                type: "Merge Duplicate",
                                                detail: `Merged rows ${resolution.row.join(", ")} into row ${Math.min(...resolution.row)}`,
                                                action: "merge",
                                                row: Math.min(...resolution.row),
                                                rows: resolution.row,
                                                mergedInto: Math.min(...resolution.row),
                                                mergedData: resolution,
                                                timestamp: new Date().toISOString()
                                            });
                                        }
                                    });
                                }
                            });

                            sessionStorage.setItem("actionDetails", JSON.stringify(actionDetails));
                            
                            // Show success message
                            Swal.fire({
                                title: "Success",
                                text: "Duplicates resolved successfully!",
                                icon: "success",
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                refreshAllDisplaysAndCheckExport();
                            });
                        }, 500);
                        return;
                    }

                    const resolution = fullyIdenticalResolutions[removalIndex];
                    if (resolution.action === "remove") {
                        const $row = $(`#row-${resolution.row}`);
                        
                        // Highlight row first
                        $row.addClass('removing');
                        
                        // Then animate removal
                        setTimeout(() => {
                            $row.addClass('fade-out-row');
                            setTimeout(() => {
                                $row.remove();
                            }, 800);
                        }, 300);
                    }
                    
                    removalIndex++;
                }, 1000); // 1 second between each removal
            }
        },
        error: function (xhr, status, error) {
            resolveModal.close();
            console.error("🚨 AJAX Error (resolveDuplicates, show.php) :", status, error, xhr.responseText);
            Swal.fire("Error", "Failed to resolve duplicates. Try again.", "error");
        }
    });
}


function resolveMissingValue() {
    let missingValues = JSON.parse(sessionStorage.getItem("missing_values") || "[]"); 
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || []);
    
    // Backup original extracted data
    if (!sessionStorage.getItem("originalExtractedData")) {
        sessionStorage.setItem("originalExtractedData", JSON.stringify(extractedData));
    }

    // Prepare backup with clean mapping
    let originalExtractedData = extractedData.map(row => ({
        row: row.row,
        data: [...row.data],
    }));
    sessionStorage.setItem("originalExtractedData", JSON.stringify(originalExtractedData));

    console.log("📌 Missing value locations:", missingValues);
    console.log("📌 First few rows of data:", extractedData.slice(0, 3));

    if (!missingValues || missingValues.length === 0) {
        Swal.fire("Error", "No stored missingValues data available.", "error");
        return;
    }

    if (!extractedData || extractedData.length === 0) {
        Swal.fire("Error", "No extracted data available to resolve missing values.", "error");
        return;
    }

    // Create HTML for the modal with table and side panel
    let tableHTML = `
        <div style="display: flex; gap: 20px;">
            <div class="resolve-modal-table" style="flex: 2; max-height: 70vh; overflow-y: auto;">
                <table>
                    <tr>`;
    
    headers.forEach(header => {
        tableHTML += `<th>${header}</th>`;
    });
    tableHTML += `</tr>`;
    
    extractedData.forEach(row => {
        tableHTML += `<tr id="row-${row.row}">`;
        row.data.forEach((cell, colIndex) => {
            // Check if cell is actually null/empty (not just in missingValues array)
            const isNullValue = cell === null || cell === '' || cell === 'null';
            
            // Only show "MISSING" if the value is actually null/empty
            const displayValue = isNullValue 
                ? `<span style="color: #ff9800; font-weight: bold;">MISSING</span>` 
                : cell;
            
            tableHTML += `<td>${displayValue}</td>`;
        });
        tableHTML += `</tr>`;
    });
    
    tableHTML += `</table></div>
            <div id="resolution-panel" style="flex: 1; padding: 10px; border-left: 1px solid #ddd; max-height: 70vh; overflow-y: auto;">
                <h3 style="color: #ff9800; margin-top: 0;">Resolution Progress</h3>
                <div id="resolution-details" style="margin-top: 20px;"></div>
            </div>
        </div>`;

    // Show modal with table and side panel
    const resolveModal = Swal.fire({
        title: "Resolving Missing Values...",
        html: tableHTML,
        width: '90%',
        showConfirmButton: false,
        allowOutsideClick: false,
        customClass: {
            container: 'resolve-modal-container',
            popup: 'resolve-modal-popup'
        },
        didOpen: () => {
            // Add CSS for animations
            $('<style>')
                .text(`
                    .filling-cell {
                        animation: cellFill 1s forwards;
                    }
                    @keyframes cellFill {
                        0% { background-color: rgba(255, 152, 0, 0.1); }
                        100% { background-color: rgba(76, 175, 80, 0.1); }
                    }
                    .new-value {
                        display: inline-block;
                        animation: fadeIn 0.5s;
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(-5px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                `)
                .appendTo('head');
        }
    });

    // Process missing values via AJAX
    $.ajax({
        url: "resolveMissingValue.php",
        type: "POST",
        data: JSON.stringify({ 
            missingValues: missingValues,
            data: extractedData
        }),
        contentType: "application/json",
        dataType: "json",
        timeout: 15000,
        success: function (response) {
            console.log("✅ Resolution response:", response);

            if (response.error) {
                resolveModal.close();
                Swal.fire("Error", response.error, "error");
            } else {
                const resolvedData = response.resolved.resolved || response.resolved;
                
                // Create a map of original null values for verification
                const originalNullCells = {};
                extractedData.forEach(row => {
                    originalNullCells[row.row] = {};
                    row.data.forEach((cell, colIndex) => {
                        const isNull = cell === null || cell === '' || cell === 'null';
                        originalNullCells[row.row][colIndex] = isNull;
                    });
                });

                // Process each resolution one by one with animation
                let index = 0;
                const processNextResolution = () => {
                    if (index >= resolvedData.length) {
                        // All resolutions complete
                        setTimeout(() => {
                            resolveModal.close();
                            sessionStorage.setItem("resolvedMVData", JSON.stringify(resolvedData));
                            applyResolutionsMissingValues(resolvedData);
                            
                            // Log actions
                            let actionDetails = JSON.parse(sessionStorage.getItem("actionDetails") || "[]");
                            console.log("🔎 actionDetails rmv (b4):", actionDetails);

                            let originalData = JSON.parse(sessionStorage.getItem("originalExtractedData") || "[]");
                            let missingValues = JSON.parse(sessionStorage.getItem("original_missing_values") || "[]");

                            resolvedData.forEach(item => {
                                const row = item.row;
                                const originalRow = originalData.find(r => Number(r.row) === Number(row));
                                if (!originalRow) return;

                                missingValues
                                    .filter(mv => Number(mv.row) === Number(row))
                                    .forEach(mv => {
                                        const headerName = headers[mv.column];
                                        const key = headers[mv.column].toLowerCase().replace(/\s+/g, "_");
                                        const resolvedValue = item[key];
                                        const originalValue = originalRow.data[mv.column];

                                        if (resolvedValue !== undefined && resolvedValue !== "" && 
                                            originalValue !== resolvedValue &&
                                            !actionDetails.some(a => a.type === "Replace Missing Value" && a.row === row && a.column === headerName)) {
                                            actionDetails.push({
                                                type: "Replace Missing Value",
                                                detail: `Filled missing value in row ${row}, column '${headerName}' with '${resolvedValue}'`,
                                                row: row,
                                                column: headerName,
                                                value: resolvedValue,
                                                timestamp: new Date().toISOString()
                                            });
                                        }
                                    });
                            });

                            sessionStorage.setItem("actionDetails", JSON.stringify(actionDetails));
                            
                            // Show success message
                            Swal.fire({
                                title: "Success",
                                text: "Missing values resolved successfully!",
                                icon: "success",
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                refreshAllDisplaysAndCheckExport();
                            });
                        }, 1000);
                        return;
                    }

                    const resolution = resolvedData[index];
                    const row = resolution.row;
                    
                    // Update the side panel with only actual null cell resolutions
                    let detailsHTML = "";
                    
                    Object.entries(resolution).forEach(([key, value]) => {
                        if (key !== "row") {
                            const headerIndex = headers.findIndex(h => 
                                h.toLowerCase().replace(/\s+/g, "_") === key);
                            
                            // Only show if this was originally a null value
                            if (headerIndex !== -1 && originalNullCells[row] && originalNullCells[row][headerIndex]) {
                                detailsHTML += `
                                <div style="margin-bottom: 10px; padding: 8px; background: #f5f5f5; border-radius: 4px;">
                                    <div>Resolved row ${row}</div>
                                    <div style="margin-left: 15px;">
                                        <span style="color: #ff9800; font-weight: bold;">${headers[headerIndex]}:</span> 
                                        <span style="font-weight: 500;">${value}</span>
                                    </div>
                                </div>`;
                                
                                // Animate the cell update
                                const $cell = $(`#row-${row} td:nth-child(${headerIndex + 1})`);
                                $cell.addClass('filling-cell');

                                // ✅ Scroll the cell into view within the modal table
                                const cellElement = $cell[0];
                                if (cellElement) {
                                    cellElement.scrollIntoView({
                                        behavior: "smooth",
                                        block: "center",
                                        inline: "center" // ensures horizontal centering if table is wide
                                    });
                                }

                                setTimeout(() => {
                                    $cell.html(`<span class="new-value">${value}</span>`);
                                    $cell.removeClass('filling-cell');
                                }, 500);

                            }
                        }
                    });
                    
                    if (detailsHTML) {
                        $("#resolution-details").append(detailsHTML);
                        // Scroll to bottom of panel
                        const panel = document.getElementById("resolution-details");
                        panel.scrollTop = panel.scrollHeight;
                    }

                    index++;
                    setTimeout(processNextResolution, 1000);
                };

                processNextResolution();
            }
        },
        error: function (xhr, status, error) {
            resolveModal.close();
            console.error("🚨 AJAX Error:", status, error, xhr.responseText);
            Swal.fire("Error", "Failed to resolve missing values. Try again.", "error");
        }
    });
}



function resolveInaccuracies() {
    let inaccuraciesValues = JSON.parse(sessionStorage.getItem("inaccuracies") || "{}");
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || "[]");

    if (!sessionStorage.getItem("originalExtractedData")) {
        sessionStorage.setItem("originalExtractedData", JSON.stringify(extractedData));
    }

    let originalExtractedData = extractedData.map(row => ({
        row: row.row,
        data: [...row.data],
    }));
    sessionStorage.setItem("originalExtractedData", JSON.stringify(originalExtractedData));

    let reportType = sessionStorage.getItem("report_type");
    if (!reportType) {
        reportType = headers.includes("Transaction Date") ? "payout" :
                      headers.includes("Return Request Date") ? "refund" : "unknown";
    }

    console.log("📌 inaccuracies value:", inaccuraciesValues);
    console.log("📌 Full Dataset:", extractedData);

    if (!inaccuraciesValues || Object.keys(inaccuraciesValues).length === 0) {
        Swal.fire("Error", "No stored inaccuraciesValues data available.", "error");
        return;
    }

    if (!extractedData || extractedData.length === 0) {
        Swal.fire("Error", "No extracted data available to resolve inaccuracies.", "error");
        return;
    }

    // 🔎 Flatten inaccuracies to array for easy lookup
    let allInaccuracies = [];
    ["incorrect_amount_calculation", "negative_values", "wrong_status_value", "misleading_timestamps"].forEach(category => {
        const entries = inaccuraciesValues[category] || [];
        if (Array.isArray(entries)) {
            entries.forEach(ia => {
                let colIndex = (ia.column !== undefined && ia.column !== null)
                    ? Number(ia.column) - 1 // adjust from 1-based to 0-based index
                    : headers.findIndex(h => h === ia.column_name);


                console.log("🔧 Flattened inaccuracy entry:", {
                    row: Number(ia.row),
                    original_column: ia.column,
                    adjusted_colIndex: colIndex,
                    column_name: ia.column_name
                });

                allInaccuracies.push({
                    row: Number(ia.row),
                    column: colIndex,
                    column_name: ia.column_name
                });
            });
        }
    });


    // ✅ Console log for highlighted inaccuracies data
    console.log("🔍 Highlighted inaccuracies (flattened):", allInaccuracies);

    // ✅ Create HTML for modal table and side panel
    let tableHTML = `
        <div style="display: flex; gap: 20px;">
            <div class="resolve-modal-table" style="flex: 2; max-height: 70vh; overflow-y: auto;">
                <table>
                    <tr>`;
    headers.forEach(header => {
        tableHTML += `<th>${header}</th>`;
    });
    tableHTML += `</tr>`;

    extractedData.forEach(row => {
        console.log("🔎 Rendering row:", row.row, "Data:", row.data);
        tableHTML += `<tr id="row-${row.row}">`;
        row.data.forEach((cell, colIndex) => {
            const isInaccurate = allInaccuracies.some(ia => {
                const sameRow = Number(ia.row) === Number(row.row);
                const sameColumn = ia.column === colIndex;
                if (sameRow && sameColumn) {
                    console.log(`✅ Will mark cell as INACCURATE at row ${row.row}, column ${colIndex} (${headers[colIndex]})`);
                }
                return sameRow && sameColumn;
            });

            const displayValue = isInaccurate
                ? `<span style="color: orange; font-weight: bold;">${cell}</span>`
                : cell;

            // ✅ Add data-row and data-column attributes (+1 to match display row/column)
            tableHTML += `<td data-row="${Number(row.row) + 1}" data-column="${colIndex + 1}">${displayValue}</td>`;
        });
        tableHTML += `</tr>`;
    });


    tableHTML += `</table></div>
            <div id="resolution-panel" style="flex: 1; padding: 10px; border-left: 1px solid #ddd; max-height: 70vh; overflow-y: auto;">
                <h3 style="color: #ff9800; margin-top: 0;">Resolution Progress</h3>
                <div id="resolution-details" style="margin-top: 20px;"></div>
            </div>
        </div>`;

    // ✅ Show modal with table and side panel
    const resolveModal = Swal.fire({
        title: "Resolving Inaccuracies...",
        html: tableHTML,
        width: '90%',
        showConfirmButton: false,
        allowOutsideClick: false,
        customClass: {
            container: 'resolve-modal-container',
            popup: 'resolve-modal-popup'
        },
        didOpen: () => {
            // ✅ Add CSS for animations
            $('<style>')
                .text(`
                    .filling-cell {
                        animation: cellFill 1s forwards;
                    }
                    @keyframes cellFill {
                        0% { background-color: rgba(233, 30, 99, 0.1); }
                        100% { background-color: rgba(76, 175, 80, 0.1); }
                    }
                    .new-value {
                        display: inline-block;
                        animation: fadeIn 0.5s;
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(-5px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                `)
                .appendTo('head');
        }
    });

    // ✅ Process inaccuracies via AJAX
    const payload = {
        ...(inaccuraciesValues.inaccuracies ?? inaccuraciesValues),
        data: extractedData,
        report_type: reportType
    };

    $.ajax({
        url: "resolveInaccuracies.php",
        type: "POST",
        data: JSON.stringify(payload),
        contentType: "application/json",
        dataType: "json",
        timeout: 15000,
        success: function (response) {
            console.log("✅ Resolution response:", response);

            if (response.error) {
                resolveModal.close();
                Swal.fire("Error", response.error, "error");
            } else {
                const resolvedData = response.resolved;

                let index = 0;

                const processNextResolution = () => {
                    if (index >= resolvedData.length) {
                        // ✅ All resolutions complete
                        setTimeout(() => {
                            resolveModal.close();
                            sessionStorage.setItem("resolvedIAData", JSON.stringify(resolvedData));
                            applyResolutionsInaccuracies(resolvedData);

                            let actionDetails = JSON.parse(sessionStorage.getItem("actionDetails") || "[]");
                            let originalData = JSON.parse(sessionStorage.getItem("originalExtractedData") || "[]");

                            resolvedData.forEach(item => {
                                const row = item.row;
                                const originalRow = originalData.find(r => Number(r.row) === Number(row));
                                if (!originalRow) return;

                                allInaccuracies
                                    .filter(ia => Number(ia.row) === Number(row))
                                    .forEach(ia => {
                                        const headerName = ia.column_name;
                                        const key = headerName.toLowerCase().replace(/\s+/g, "_");
                                        const resolvedValue = item[key];
                                        const originalValue = originalRow.data[ia.column];

                                        if (resolvedValue !== undefined && resolvedValue !== "" &&
                                            originalValue !== resolvedValue &&
                                            !actionDetails.some(a => a.type === "Resolve Inaccuracy" && a.row === row && a.column === headerName)) {
                                            actionDetails.push({
                                                type: "Resolve Inaccuracy",
                                                detail: `Corrected value in row ${row}, column '${headerName}' from '${originalValue}' to '${resolvedValue}'`,
                                                row: row,
                                                column: headerName,
                                                value: resolvedValue,
                                                timestamp: new Date().toISOString()
                                            });
                                        }
                                    });
                            });

                            sessionStorage.setItem("actionDetails", JSON.stringify(actionDetails));

                            Swal.fire({
                                title: "Success",
                                text: "Inaccuracies resolved successfully!",
                                icon: "success",
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                refreshAllDisplaysAndCheckExport();
                            });
                        }, 1000);
                        return;
                    }

                    const resolution = resolvedData[index];
                    const row = resolution.row;

                    let detailsHTML = "";

                    Object.entries(resolution).forEach(([key, value]) => {
                        if (key !== "row") {
                            const headerIndex = headers.findIndex(h => h.toLowerCase().replace(/\s+/g, "_") === key);
                            const isInaccurate = allInaccuracies.some(ia => Number(ia.row) === Number(row) && ia.column_name.toLowerCase().replace(/\s+/g, "_") === key);

                            if (headerIndex !== -1 && isInaccurate) {
                                detailsHTML += `
                                <div style="margin-bottom: 10px; padding: 8px; background: #f5f5f5; border-radius: 4px;">
                                    <div>Resolved row ${Number(row) + 1}</div>
                                    <div style="margin-left: 15px;">
                                        <span style="color: #ff9800; font-weight: bold;">${headers[headerIndex]}:</span> 
                                        <span style="font-weight: 500;">${value}</span>
                                    </div>
                                </div>`;

                                const $cell = $(`#row-${row} td:nth-child(${headerIndex + 1})`);
                                console.log(`✅ Updating cell at row ${Number(row) + 1}, column ${headerIndex + 1} to value:`, value);

                                $cell.addClass('filling-cell');

                                // ✅ Scroll cell into view smoothly *before* updating value
                                const cellElement = $cell[0];
                                if (cellElement) {
                                    cellElement.scrollIntoView({
                                        behavior: "smooth", // smooth instead of auto
                                        block: "center",
                                        inline: "center"
                                    });
                                }

                                // 🔧 If your container requires manual horizontal scroll adjustment
                                const scrollableContainer = $(".resolve-modal-table"); // adjust selector as needed
                                if (scrollableContainer.length) {
                                    const container = scrollableContainer[0];
                                    const cellRect = cellElement.getBoundingClientRect();
                                    const containerRect = container.getBoundingClientRect();

                                    const offsetLeft = cellRect.left - containerRect.left + container.scrollLeft - (container.clientWidth / 2) + ($cell.outerWidth() / 2);

                                    // ✅ Smooth horizontal scroll using native behavior
                                    container.scrollTo({
                                        left: offsetLeft,
                                        behavior: "smooth"
                                    });
                                }

                                // ✅ Increase delay before updating value to make it *feel slower*
                                setTimeout(() => {
                                    $cell.html(`<span class="new-value">${value}</span>`);
                                    // Keep highlight for longer before removing
                                    setTimeout(() => {
                                        $cell.removeClass('filling-cell');
                                    }, 800); // keep highlight for additional 800ms after update
                                }, 1200); // wait 1.2s before replacing value



                            }
                        }
                    });

                    if (detailsHTML) {
                        $("#resolution-details").append(detailsHTML);
                        const panel = document.getElementById("resolution-details");
                        panel.scrollTop = panel.scrollHeight;
                    }

                    index++;
                    setTimeout(processNextResolution, 1000);
                };

                processNextResolution();
            }
        },
        error: function (xhr, status, error) {
            resolveModal.close();
            console.error("🚨 AJAX Error:", status, error, xhr.responseText);
            Swal.fire("Error", "Failed to resolve inaccuracies. Try again.", "error");
        }
    });
}




function applyResolutions(resolvedData) {
    console.log("🔍 Raw resolvedData in applyresolution (show.php):", resolvedData);

    if (!resolvedData || typeof resolvedData !== "object") {
        console.error("🚨 Invalid resolvedData format, expected an object:", resolvedData);
        return;
    }

    let extractedData = JSON.parse(sessionStorage.getItem("extractedData") || "[]");

    // Collect all rows that need to be removed
    let rowsToRemove = [];

    let rowsToMerge = {};
    let mergedRowsList = [];

    let resolvedItems = resolvedData.resolved || resolvedData; // Handle nested object case

    Object.values(resolvedItems).forEach(resolutionArray => {
        if (Array.isArray(resolutionArray)) {
            resolutionArray.forEach(resolution => {
                if (resolution.action === "remove") {
                    rowsToRemove.push(Number(resolution.row)); // Ensure it's a number
                } else if (resolution.action === "merge") {
                    // Use the resolution itself as merged data
                    const { row: mergedRows, ...mergedContent } = resolution;

                    mergedRows.forEach(rowNum => {
                        rowsToRemove.push(Number(rowNum));
                        rowsToMerge[rowNum] = mergedContent;
                    });

                    mergedRowsList.push({ ...mergedContent, row: mergedRows });  // Attach back the row list
                }
            });
        }
    });

    console.log("🗑️ Rows to Remove (applyResolutions,show.php):", rowsToRemove);

    console.log("🔄 Rows to Merge (applyResolutions, show.php):", rowsToMerge);

    console.log("📋 Extracted Data Before processing (applyResolutions,show.php) :", extractedData);

    // 🔍 Ensure row.row is treated as a number for correct filtering
    extractedData = extractedData.filter(row => !rowsToRemove.includes(Number(row.row)));

    // 🔁 Process each merged group
    mergedRowsList.forEach(mergedRow => {
        let newMergedRow = null;

        if ("gross_sales_amount" in mergedRow && "net_payout_amount" in mergedRow) {
            // Payout Report (8 columns)
            newMergedRow = {
                row: mergedRow.row,
                data: [
                    String(mergedRow.order_id),
                    mergedRow.transaction_date,
                    String(mergedRow.gross_sales_amount),
                    String(mergedRow["platform_fees_(commissions_or_service_charges)"]),
                    String(mergedRow.transaction_fees),
                    String(mergedRow.shipping_fees),
                    String(mergedRow.refunds_issued),
                    String(mergedRow.net_payout_amount)
                ]
            };
        } else if ("refund_amount" in mergedRow && "return_status" in mergedRow) {
            // Refund Report (6 columns)
            newMergedRow = {
                row: mergedRow.row,
                data: [
                    String(mergedRow.order_id),
                    mergedRow.product_name,
                    mergedRow.return_request_date,
                    String(mergedRow.refund_amount),
                    mergedRow.reason_for_return,
                    mergedRow.return_status
                ]
            };
        } else {
            console.error("🚨 Unexpected mergedRow format:", mergedRow);
            return;
        }

        if (newMergedRow) {
            const firstRow = Math.min(...mergedRow.row.map(Number));

            // Remove old merged rows again just in case
            extractedData = extractedData.filter(row => !mergedRow.row.includes(Number(row.row)));

            console.log("🧩 Final mergedRow to insert:", {
                ...newMergedRow,
                row: firstRow
            });

            extractedData.push({ ...newMergedRow, row: firstRow });
        }
    });

    extractedData.sort((a, b) => Number(a.row) - Number(b.row));
    
    console.log("✅ After process, Extracted Data (applyResolutions,show.php) :", extractedData);

    sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(extractedData));
    sessionStorage.setItem("extractedData", JSON.stringify(extractedData)); 
    displayExtractedData();

    sessionStorage.removeItem("duplicateData"); 
    sessionStorage.setItem("duplicateData", JSON.stringify({})); 
    displayDuplicateData(); // Refresh UI after clearing

}


function applyResolutionsMissingValues(resolvedData) {
    console.log("🔄 Applying Resolutions to Missing Values:", resolvedData);

    // Get the extracted data to update its actual values
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || "[]");

    resolvedData.forEach(item => {
        const rowIndex = Number(item.row);
        const extractedRow = extractedData.find(r => Number(r.row) === rowIndex);

        if (!extractedRow) {
            console.warn(`⚠️ No matching row found for row index: ${rowIndex}`);
            return;
        }

        if (!extractedRow || !Array.isArray(extractedRow.data)) return;

        Object.entries(item).forEach(([key, value]) => {
            if (key === "row") return;

            const normalizeHeader = h => h
                .toLowerCase()
                .replace(/\s+/g, "_")
                .replace(/\(.*?\)/g, "") // remove any parentheses and their contents
                .replace(/_+/g, "_")     // collapse multiple underscores
                .replace(/^_|_$/g, "");  // trim leading/trailing underscores

            const normalizedHeaders = headers.map(normalizeHeader);

            const colIndex = normalizedHeaders.indexOf(normalizeHeader(key));

            if (colIndex >= 0) {
                console.log(`✅ Filling resolved [Row ${rowIndex}] Column: ${key} =>`, value);
                extractedRow.data[colIndex] = value;
            }
        });
    });

    // ✅ Save updated extractedData back to sessionStorage
    sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(extractedData));
    sessionStorage.setItem("extractedData", JSON.stringify(extractedData));

    // ✅ Clear missing values now that they’ve been resolved
    sessionStorage.removeItem("missing_values");

    // ✅ Refresh UI
    displayExtractedData();        // Re-render the table
    displayMissingValues();        // Should now show "No missing values"
}


function applyResolutionsInaccuracies(resolvedData) {
    console.log("🔄 Applying Resolutions to inac Values:", resolvedData);

    // Get the extracted data to update its actual values
    let extractedData = JSON.parse(sessionStorage.getItem("extractedDataWithRowNumbers") || "[]");
    let headers = JSON.parse(sessionStorage.getItem("headers") || "[]");

    resolvedData.forEach(item => {
        const rowIndex = Number(item.row);
        const extractedRow = extractedData.find(r => Number(r.row) === rowIndex);

        if (!extractedRow || !Array.isArray(extractedRow.data)) return;

        Object.entries(item).forEach(([key, value]) => {
            if (key === "row") return;

            const normalizeHeader = h => h
                .toLowerCase()
                .replace(/\s+/g, "_")
                .replace(/\(.*?\)/g, "") // remove any parentheses and their contents
                .replace(/_+/g, "_")     // collapse multiple underscores
                .replace(/^_|_$/g, "");  // trim leading/trailing underscores

            const normalizedHeaders = headers.map(normalizeHeader);

            const colIndex = normalizedHeaders.indexOf(normalizeHeader(key));

            if (colIndex >= 0) {
                console.log(`✅ Filling resolved [Row ${rowIndex}] Column: ${key} =>`, value);
                extractedRow.data[colIndex] = value;
            }
        });
    });

    // ✅ Save updated extractedData back to sessionStorage
    sessionStorage.setItem("extractedDataWithRowNumbers", JSON.stringify(extractedData));
    sessionStorage.setItem("extractedData", JSON.stringify(extractedData));

    // ✅ Clear now that they’ve been resolved
    sessionStorage.removeItem("inaccuracies");

    // ✅ Refresh UI
    refreshAllDisplaysAndCheckExport();
}




function showAlert(icon, title, text) {
    //const theme = localStorage.getItem("theme") || "dark"; // Get current theme

    Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonColor: "#ff9800",
        //background: theme === "dark" ? "#333" : "#fff",  // Match dark/light mode
        //color: theme === "dark" ? "#fff" : "#000",
        background: "#fff",  // Match dark/light mode
        color: "#000",
        width: "380px",
        padding: "12px",
        customClass: {
            popup: "custom-alert"
        }
    }).then(() => {
        //if (callback) callback(); // Call the function after alert closes
    });
}




</script>

</body>
</html>
