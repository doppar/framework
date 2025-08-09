<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>403 Access Forbidden</title>
    </head>
    <body
        style="margin:0; font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif; background-color:#f7fafc; min-height:100vh; display:flex; justify-content:center; align-items:center;">
        <div style="max-width:36rem; margin:auto; padding:1.5rem;">
            <div style="display:flex; align-items:center; padding-top:2rem;">
                <div
                    style="padding:0 1rem 0 0; font-size:1.125rem; color:#718096; border-right:1px solid #cbd5e0; letter-spacing:0.05em;">
                    403
                </div>
                <div
                    style="margin-left:1rem; font-size:1.125rem; color:#718096; text-transform:uppercase; letter-spacing:0.05em;">
                    <?php echo $message === 'An error occurred.' ? 'Access to the resource is forbidden': $message; ?>
                </div>
            </div>
        </div>
    </body>
</html>
