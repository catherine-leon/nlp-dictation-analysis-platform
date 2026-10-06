function exportCSV() {

let table = document.getElementById("resultsTable");

let rows = table.querySelectorAll("tr");

let csv = [];

rows.forEach(row => {

let cols = row.querySelectorAll("th, td");

let rowData = [];

cols.forEach(col => {
rowData.push(col.innerText);
});

csv.push(rowData.join(","));

});

let csvFile = new Blob([csv.join("\n")], { type: "text/csv" });

let downloadLink = document.createElement("a");

downloadLink.download = "resultats.csv";
downloadLink.href = window.URL.createObjectURL(csvFile);
downloadLink.style.display = "none";

document.body.appendChild(downloadLink);

downloadLink.click();

}