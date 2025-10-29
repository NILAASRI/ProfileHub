// profile.js
// Replace with your Heroku backend URL (do NOT include trailing slash)
const API_BASE = "https://profilehub-backend.onrender.com";

$(document).ready(function () {
  // Accept session from either localStorage or sessionStorage
  const sessionId = localStorage.getItem("sessionId") || sessionStorage.getItem("sessionId");
  if (!sessionId) {
    alert("You are not logged in!");
    window.location.href = "login.html";
    return;
  }

  // --- Fetch Profile (profile.php expects JSON in php://input) ---
  function fetchProfile() {
    $.ajax({
      url: API_BASE + "/profile.php",
      type: "POST",
      dataType: "json",
      contentType: "application/json",
      data: JSON.stringify({ sessionId: sessionId, action: "fetch" }),
      crossDomain: true,
      success: function (res) {
        console.log("Profile fetch response:", res);
        if (res.status === "success" && res.data) {
          $("#detailEmail").text(res.data.email || "");
          $("#detailName").text(res.data.name || "");
          $("#detailDob").text(res.data.dob || "");
          $("#detailContact").text(res.data.contact || "");
          $("#detailAge").text(res.data.age || "");
          $("#detailAddress").text(res.data.address || "");
          $("#detailGender").text(res.data.gender || "");
          $("#greeting").text(`Welcome, ${res.data.name || "User"}`);

          $("#editName").val(res.data.name || "");
          $("#editDob").val(res.data.dob || "");
          $("#editContact").val(res.data.contact || "");
          $("#editAge").val(res.data.age || "");
          $("#editAddress").val(res.data.address || "");
          $("#editGender").val(res.data.gender || "");
        } else {
          alert(res.msg || "Session expired, please login again.");
          // clear storage
          localStorage.removeItem("sessionId");
          sessionStorage.removeItem("sessionId");
          window.location.href = "login.html";
        }
      },
      error: function (xhr, status, error) {
        console.error("Profile AJAX Error:", status, error);
        console.log("Raw Response:", xhr.responseText);
        alert("Unable to fetch profile. Try logging in again.");
      }
    });
  }

  // initial fetch
  fetchProfile();

  // --- Save Changes (send JSON) ---
  $("#saveChanges").on("click", function () {
    const name = $("#editName").val().trim();
    const dob = $("#editDob").val();
    const contact = $("#editContact").val().trim();
    const age = $("#editAge").val();
    const address = $("#editAddress").val().trim();
    const gender = $("#editGender").val();

    if (!name || !dob || !contact) {
      alert("Please fill all required fields (Name, DOB, Contact).");
      return;
    }

    const $btn = $(this);
    $btn.prop("disabled", true);

    const payload = {
      sessionId: sessionId,
      action: "update",
      name: name,
      dob: dob,
      contact: contact,
      age: age ? parseInt(age, 10) : 0,
      address: address,
      gender: gender
    };

    $.ajax({
      url: API_BASE + "/profile.php",
      type: "POST",
      dataType: "json",
      contentType: "application/json",
      data: JSON.stringify(payload),
      crossDomain: true,
      success: function (res) {
        console.log("Profile update response:", res);
        if (res.status === "success") {
          alert(res.msg || "Profile updated successfully");
          fetchProfile();
          // if using bootstrap modal:
          $("#editModal").modal && $("#editModal").modal("hide");
        } else {
          alert(res.msg || "Failed to update profile");
        }
      },
      error: function (xhr, status, error) {
        console.error("Profile update AJAX error:", status, error);
        console.log("Raw Response:", xhr.responseText);
        alert("Server error while updating profile.");
      },
      complete: function () {
        $btn.prop("disabled", false);
      }
    });
  });

  // --- Logout ---
  $("#logoutBtn").on("click", function () {
    // Optionally clear session in backend by calling a logout endpoint if you implement one
    localStorage.removeItem("sessionId");
    sessionStorage.removeItem("sessionId");
    window.location.href = "login.html";
  });
});
