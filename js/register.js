// register.js
const API_BASE = "https://guvi-intern-md3o.onrender.com/php";

$(document).ready(function () {

  // Step navigation
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

  // Registration submit
  $("#registerBtn").on("click", function () {
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
      url: `${API_BASE}/register.php`,
      type: "POST",
      data: payload,
      dataType: "json",
      success: function (res) {
        console.log("Response:", res);
        if (res.status === "success") {
          alert(res.msg);
          $("#registerForm")[0].reset();
          $("#step2").hide();
          $("#step1").show();
        } else {
          alert(res.msg || "Registration failed.");
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error:", status, error);
        console.log("Response Text:", xhr.responseText);
        alert("Error connecting to the server. Try again later.");
      }
    });
  });
});
