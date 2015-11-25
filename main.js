var entropiCurOp;
var entropiRequest;

window.addEventListener('load', function(){
    var form = jQuery("#entropi-run-parser");
    if (form.find('button').text() == 'Stop') {
        entropiCurOp = "run";
    }

    form.on("submit", function(e){
        e.preventDefault();

        if (entropiCurOp!='run') {
            form.find('input[name="operation"]').val("run");
            var buttonText = "Stop";
            entropiCurOp = "run";
        } else {
            form.find('input[name="operation"]').val("stop");
            var buttonText = "Stopping. Refresh this page in a while to confirm the stop.";
            entropiCurOp = "stop";
        }
        entropiRequest = jQuery.ajax({
            url: window.location.href,
            type: "post",
            data: jQuery(this).serialize(),
            dataType: "json",
            success: function(resp) {
                if (resp.status == 'ok') {
                    entropiCurOp = false;
                }
            }
        });

        form.find("button").text(buttonText);
    });
});