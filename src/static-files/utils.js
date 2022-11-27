

function show_rows(rows, on) {
    let r = "";
    for(r of rows) {
        document.getElementById(r).style = on ? "visibility: visible" : "visibility: collapse";
    }
}

function show_rows_on_checkbox(rows, checkbox_id) {
    let on = document.getElementById(checkbox_id).checked;
    show_rows(rows, on);
}
