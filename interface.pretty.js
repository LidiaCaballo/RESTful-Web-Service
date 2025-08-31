// Wait for the DOM to be fully loaded before executing the script
document.addEventListener("DOMContentLoaded", function() {
    // Get references to DOM elements:
    var e = document.getElementById("send-btn"),      // Send button
        t = document.getElementById("clear-btn"),     // Clear button
        a = document.getElementById("method"),        // HTTP method dropdown
        d = document.getElementById("path"),          // Resource path input
        o = document.getElementById("body"),          // Request body textarea
        s = document.getElementById("status-code"),   // Status code display
        l = document.getElementById("response-body"); // Response body display

    // Add click event listener to the send button
    e.addEventListener("click", function() {
        // Get values from form inputs:
        var e = a.value,      // Selected HTTP method (GET/POST/PATCH/DELETE)
            t = d.value.trim(), // Resource path (trimmed)
            n = o.value.trim(); // Request body (trimmed)
        
        // Validate that a resource path was provided
        if (t) {
            // For POST/PATCH requests, ensure there's a request body
            if ("POST" !== e && "PATCH" !== e || n) {
                // Construct the full API URL and request options
                if (t = "https://student.csc.liv.ac.uk/~hslcabal/v1/" + t, // Prepend base URL
                    e = { // Create request options object
                        method: e, // HTTP method
                        headers: { // Request headers
                            "Content-Type": "application/json" // Specify JSON content
                        }
                    },
                    n) { // If there's a request body
                    try {
                        JSON.parse(n), // Validate JSON syntax
                        e.body = n    // Add validated JSON to request body
                    } catch (e) {
                        // Show alert for invalid JSON and exit
                        return void alert("Invalid JSON: " + e.message)
                    }
                }
                // Make the API request using fetch
                fetch(t, e)
                    .then(e => {
                        // Display HTTP status code
                        s.textContent = e.status + " " + e.statusText;
                        // Parse response as JSON (or return null if parsing fails)
                        return e.json().catch(() => null)
                    })
                    .then(e => {
                        // Display formatted JSON response
                        l.textContent = JSON.stringify(e, null, 2)
                    })
                    .catch(e => {
                        // Handle fetch errors
                        s.textContent = "Error",
                        l.textContent = e.message
                    })
            } else {
                // Alert when POST/PATCH requests are missing a body
                alert("Please provide a JSON body for POST/PATCH requests")
            }
        } else {
            // Alert when no resource path is provided
            alert('Please enter a resource path (e.g., "teams/1/players")')
        }
    }),
    
    // Add click event listener to the clear button
    t.addEventListener("click", function() {
        // Reset the display areas
        s.textContent = "-",  // Clear status code
        l.textContent = "-"   // Clear response body
    })
});