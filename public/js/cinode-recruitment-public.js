jQuery(document).ready(function ($) {
  $("#Attachments").change(function () {
    $("#file-name").text(this.files[0].name);
  });

  $(this).on("submit", (event) => {
    $(".spinner").show();
    const email = $("#email-input");
    const first_name = $("#first_name-input");
    const last_name = $("#last_name-input");
    const phone = $("#phone-input");
    const description = $("textarea#description-input");
    const linkedInUrl = $("#LinkedInUrl");
    const companyAddressSelect = $("#companyAddressId option:selected");
    const multiplePipeline = $("#selectedPipelineId option:selected").val();
    const multipleStageId = $("#selectedPipelineId option:selected").attr(
      "stageId"
    );
    const file = $("input#Attachments")[0].files[0];
    const terms = $("#terms");

    event.preventDefault();

    var formData = new FormData();
    if (terms[0].checked) {
      $("#terms-validate").hide();
      errorMessages = validateRequiredInputs();
      if (errorMessages.length === 0) {
        formData.append("files", file);
        formData.append("firstName", first_name.val());
        formData.append("lastName", last_name.val());
        formData.append("email", email.val());
        formData.append("phone", phone.val());
        formData.append("description", description.val());
        formData.append("linkedInUrl", linkedInUrl.val());
        formData.append("state", 0);
        formData.append("currencyId", currencyId);

        if (pipelineId) {
          formData.append("pipelineId", pipelineId);
        } else if (multiplePipeline) {
          formData.append("pipelineId", multiplePipeline);
        }

        if (pipelineStageId) {
          formData.append("pipelineStageId", pipelineStageId);
        } else if (multipleStageId) {
          formData.append("pipelineStageId", multipleStageId);
        }

        if (recruitmentManagerId) {
          formData.append("recruitmentManagerId", recruitmentManagerId);
        }
        if (teamId) {
          formData.append("teamId", teamId);
        }
        if (companyAddressId) {
          formData.append("companyAddressId", companyAddressId);
        } else if (companyAddressSelect.length == 1) {
          formData.append("companyAddressId", companyAddressSelect.val());
        } else {
          formData.append("companyAddressId", "");
        }

        if (recruitmentSourceId) {
          formData.append("recruitmentSourceId", recruitmentSourceId);
        }
        formData.append("campaignCode", campaignCode);
        formData.append("currencyId", currencyId);

        submitRequest(formData);
      } else {
        $(".spinner").hide();
      }
    } else {
      $("#terms-validate").show();
      $(".spinner").hide();
    }

    function validateRequiredInputs() {
      let errorMessage = [];

      if (email.val() === "") {
        $("#email-required").show();
        errorMessage.push("Aplicant email is missing");
      } else {
        errorMessage.filter(
          (message) => message !== "Aplicant address is missing"
        );
        $("#email-required").hide();
      }
      var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
      if (!emailReg.test(email.val())) {
        $("#email-required").show();
      }

      if (first_name.val() === "") {
        errorMessage.push("First Name is missing");
        $("#first_name-required").show();
      } else {
        errorMessage.filter((message) => message !== "First Name is missing");
        $("#first_name-required").hide();
      }
      if (last_name.val() === "") {
        errorMessage.push("Last Name is missing");
        $("#last_name-required").show();
      } else {
        errorMessage.filter((message) => message !== "Last Name is missing");
        $("#last_name-required").hide();
      }

      return errorMessage;
    }
  });

  function submitRequest(data) {
    $(".spinner").show();
    var url = cinode_url.site_url;
    var path = url + "/wp-json/cinode/v2/cinode-recruitment";

    $.ajax({
      type: "POST",
      url: path,
      data: data,
      contentType: false,
      processData: false,
      success: function (response) {
        if (response.response.code == 201) {
          $(".spinner").hide();
          $("#successful-submit-msg").show(300);
          setTimeout(function () {
            $("#successful-submit-msg").fadeOut("fast");
          }, 5000);
          $("input:checkbox").removeAttr("checked");
          $(":input", "#cinode-form")
            .not(":button, :submit, :reset, :hidden")
            .val("");
          $("#file-name").text("");
        } else {
          $("#unsuccessful-submit-msg").show(300);
          setTimeout(function () {
            $("#unsuccessful-submit-msg").fadeOut("fast");
          }, 6000);
          $(".spinner").hide();
        }
      },
    });
  }
});
