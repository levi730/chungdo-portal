<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta content="telephone=no" name="format-detection" />
    <title></title>
    <style type="text/css" data-premailer="ignore">
        @import url(https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700);
        @import url(https://fonts.googleapis.com/css?family=Cormorant:300,400,500,600,700);
    </style>
    <style data-premailer="ignore">
        @media screen and (max-width: 600px) {
            u+.body {
                width: 100vw !important;
            }
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
            font-size: inherit !important;
            font-family: inherit !important;
            font-weight: inherit !important;
            line-height: inherit !important;
        }
    </style>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif;
        }

        img {
            -ms-interpolation-mode: bicubic;
        }

        .box {
            border-color: #eee !important;
        }
    </style>
    <![endif]-->
    <link rel="stylesheet" href="{{ url('css/email.css') }}" />
</head>

<body class="bg-body">
<center>
    <table class="main bg-body" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" valign="top">
                <!--[if (gte mso 9)|(IE)]>
                <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td align="center" valign="top" width="640">
                <![endif]-->
                <span class="preheader">@yield('preheader')</span>
                <table class="wrap" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="p-sm">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="py-lg">
                                        <table cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td>
                                                    <a href="{{ config('app.url') }}"><img src="{{ url('img/CDKTKD_logo_email.png') }}" width="150" height="102" alt="Chung Do Association" /></a>
                                                </td>
                                                <td>
                                                    <span style="font-family: 'Cormorant', serif; font-size: 32px;">Chung Do Association</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div class="main-content">
                                @yield('main')
                            </div>
                            <table cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="py-xl">
                                        <table class="font-sm text-center text-muted" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="px-lg">
                                                    If you have any questions, feel free to message us at <a href="mailto:support@chungdo.org" class="text-muted">support@chungdo.org</a>.
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="pt-md">
                                                    {{--You are receiving this email because you have registered at <a href="https://chungdo.org" class="text-muted">https://chungdo.org</a>.<br>
                                                    <a href="https://chungdo.org/email-management" class="text-muted">Unsubscribe or Manage Email Preferences</a>--}}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</center>
</body>

</html>
