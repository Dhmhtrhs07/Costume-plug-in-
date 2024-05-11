var map;

// Function to add markers to the map
function addMarker(user) {
    var popupContent = '<b>Information</b><br>' +
                       '<b>Company Name:</b> ' + user.name + '<br>' +
                       '<b>Email:</b> ' + user.email + '<br>' +
                       '<b>Address:</b> ' + user.address;
    // Append website if available
    if (user.phone) {
        popupContent += '<br>Website: ' + user.phone;
    }

    // Create a marker and add it to the map
    var marker = L.marker([user.latitude, user.longitude]).addTo(map);
    marker.bindPopup(popupContent);
}

// Initialize the map and load user data
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the map on the "map" div with a given center and zoom
    map = L.map('map').setView([54.5260, 15.2551], 4);
    // Add OpenStreetMap tiles to the map
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Make AJAX request to fetch verified user data
    var xhr = new XMLHttpRequest();
    xhr.open('GET', uvmp_ajax_object.ajax_url + '?action=get_verified_users', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            // Parse the JSON response and add markers for each user
            var users = JSON.parse(xhr.responseText);
            users.forEach(addMarker);
        } else {
            // Handle HTTP error (status code not 200)
            console.error('Failed to load user data:', xhr.statusText);
        }
    };
    xhr.onerror = function() {
        // Handle network error
        console.error('Network error while fetching user data');
    };
    xhr.send();
});
