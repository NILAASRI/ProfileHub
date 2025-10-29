// login.js
// Replace with your Heroku backend URL (do NOT include trailing slash)
const API_BASE = "https://profilehub-backend.onrender.com/php";

$(document).ready(function() {
  $("#loginForm").on("submit", function(e) {
    e.preventDefault();

    const email = $("#loginEmail").val().trim();
    const password = $("#loginPassword").val();
    const remember = $("#rememberMe").is(":checked");

    if (!email || !password) {
      alert("Please enter both email and password");
      return;
    }

    const $submit = $("#loginForm button[type='submit']");
    $submit.prop("disabled", true);

    $.ajax({
      url: API_BASE + "/login.php",
      method: "POST",
      dataType: "json",
      data: { email: email, password: password }, // form-encoded for login.php
      crossDomain: true,
      beforeSend: function() {
        console.log("Logging in...");
      },
      success: function(res) {
        console.log("Server Response:", res);
        if (res.status === "success" && res.sessionId) {
          // Store session depending on Remember Me
          if (remember) {
            localStorage.setItem("sessionId", res.sessionId);
            sessionStorage.removeItem("sessionId"); // keep only in local
          } else {
            sessionStorage.setItem("sessionId", res.sessionId);
            localStorage.removeItem("sessionId"); // keep only in session
          }

          // redirect to profile page
          alert("Login successful!");
          window.location.href = "profile.html";
        } else {
          alert(res.msg || "Invalid email or password");
        }
      },
      error: function(xhr, status, error) {
        console.error("Login error:", status, error);
        console.log("Response:", xhr.responseText);
        alert("Server error. Please try again.");
      },
      complete: function() {
        $submit.prop("disabled", false);
      }
    });
  });
});
