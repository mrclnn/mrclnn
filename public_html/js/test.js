
xhr = new XMLHttpRequest;
xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
        let response = JSON.parse(xhr.responseText);
        console.log(response);
    }
}
xhr.open("GET", 'https://json.geoiplookup.io/api')
xhr.send();