// register.js
// Replace with your Heroku backend URL (do NOT include trailing slash)
const API_BASE = "https://profilehub-backend.onrender.com/php"; 

$(document).ready(function () {
  // Multi-step navigation
  $("#nextBtn").on("click", function () {
    const name = $("#name").val().trim();
    const email = $("#email").val().trim();
    const password = $("#password").val();
    const confirmPassword = $("#confirmPassword").val();
    const terms = $("#terms").is(":checked");

    if (!name || !email || !password || !confirmPassword) {
      alert("Please fill all fields.");
      return;
    }
    if (password !== confirmPassword) {
      alert("Passwords do not match.");
      return;
    }
    if (!terms) {
      alert("Please accept the terms and conditions.");
      return;
    }

    $("#step1").hide();
    $("#step2").show();
  });

  $("#backBtn").on("click", function () {
    $("#step2").hide();
    $("#step1").show();
  });

  // Submit registration (form-encoded because register.php expects $_POST)
  $("#registerBtn").on("click", function () {
    const $btn = $(this);
    $btn.prop("disabled", true);

    const payload = {
      name: $("#name").val().trim(),
      email: $("#email").val().trim(),
      password: $("#password").val(),
      confirmPassword: $("#confirmPassword").val(),
      dob: $("#dob").val(),
      age: $("#age").val(),
      phone: $("#phone").val().trim(),
      address: $("#address").val().trim(),
      gender: $("#gender").val()
    };

    $.ajax({
      url: API_BASE + "/register.php",
      type: "POST",
      dataType: "json",
      data: payload, // sent as application/x-www-form-urlencoded
      crossDomain: true,
      beforeSend: function () {
        console.log("Sending registration request...");
      },
      success: function (res) {
        console.log("Response:", res);
        if (res.status === "success") {
          alert(res.msg || "Registered successfully. Please login.");
          $("#registerForm")[0].reset();
          $("#step2").hide();
          $("#step1").show();
          // optional redirect to login:
          // window.location.href = "login.html";
        } else {
          alert(res.msg || "Registration failed. See console for details.");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", status, error);
        console.log("Response Text:", xhr.responseText);
        alert("Error during registration. Check console logs.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      }
    });
  });
});
