// Nizam -- Windows launcher and native application window.
//
// Apache (NizamApache) and MariaDB (NizamMariaDB) are installed as Windows
// services with Automatic startup, so in normal daily use they are already
// running by the time this launcher runs at all -- a school user should
// never need to start/stop anything, and this launcher never requests
// elevation. Its job on a normal day is exactly three steps: confirm the
// app answers on localhost, open a native window showing it (WebView2, not
// a browser tab -- no address bar, no browser chrome), and exit cleanly
// when that window closes. The service-start attempt below is a best-effort
// fallback for the rare case (a fresh boot mid-startup, or a service that
// was manually stopped) where it isn't already up.
//
// The underlying application is untouched by any of this: WebView2 is
// simply pointed at the exact same http://127.0.0.1:8890/ every browser-
// based test in this project has always used. Nothing about the PHP app,
// Apache config, or MariaDB setup changes because of how it's displayed.
using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.Runtime.InteropServices;
using System.ServiceProcess;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

internal static class NizamLauncher
{
    private const string ApacheServiceName = "NizamApache";
    private const string MariaDbServiceName = "NizamMariaDB";
    private const string AppUrl = "http://127.0.0.1:8890/";
    private const string WindowTitle = "Nizam";
    private const int HealthCheckTimeoutSeconds = 30;

    [STAThread]
    private static void Main()
    {
        // Enable high-DPI support for crisp rendering on modern displays
        if (Environment.OSVersion.Version.Major >= 6)
        {
            SetProcessDPIAware();
        }
        
        bool createdNew;
        using (var instanceLock = new Mutex(true, "Global\\NizamLauncherSingleInstance", out createdNew))
        {
            if (!createdNew)
            {
                // Nizam is already running somewhere on this machine --
                // bring its window to the front instead of starting a
                // second, redundant instance. If that fails for any reason
                // (window already closing, focus denied by Windows), this
                // is a quiet no-op: a second copy is still never started.
                ActivateExistingWindow();
                return;
            }

            try
            {
                TryStartService(MariaDbServiceName);
                TryStartService(ApacheServiceName);

                if (!WaitUntilHealthy())
                {
                    ShowFriendlyError(
                        "Nizam could not start",
                        "Nizam's local service did not respond in time. Try restarting your computer. " +
                        "If this keeps happening, ask your administrator to check the Nizam log files."
                    );
                    return;
                }

                RunNativeWindow();
            }
            catch (Exception ex)
            {
                LogStartupFailure(ex);
                ShowFriendlyError(
                    "Nizam could not start",
                    "An unexpected problem prevented Nizam from starting. Try restarting your computer, " +
                    "and contact your administrator if this keeps happening."
                );
            }

            instanceLock.ReleaseMutex();
        }
    }

    /// <summary>
    /// Best-effort only: services are Automatic-startup and normally already
    /// running, so a permission failure here (a non-admin user, day to day)
    /// is not treated as fatal -- the health check below gives the service
    /// a real chance to finish starting on its own regardless.
    /// </summary>
    private static void TryStartService(string serviceName)
    {
        try
        {
            using (var controller = new ServiceController(serviceName))
            {
                if (controller.Status == ServiceControllerStatus.Running)
                {
                    return;
                }
                if (controller.Status == ServiceControllerStatus.StartPending)
                {
                    controller.WaitForStatus(ServiceControllerStatus.Running, TimeSpan.FromSeconds(HealthCheckTimeoutSeconds));
                    return;
                }
                controller.Start();
                controller.WaitForStatus(ServiceControllerStatus.Running, TimeSpan.FromSeconds(HealthCheckTimeoutSeconds));
            }
        }
        catch (InvalidOperationException)
        {
            // Service not installed/found -- surfaces as an unhealthy result
            // from WaitUntilHealthy() instead, reported in plain language.
        }
        catch (System.ComponentModel.Win32Exception)
        {
            // No permission to control the service from this account --
            // expected for a non-admin user when Automatic startup hasn't
            // finished yet; fall through and let the health check decide.
        }
        catch (System.ServiceProcess.TimeoutException)
        {
            // Kept starting past our patience window; the health check
            // below still gets its own full timeout to confirm either way.
        }
    }

    private static bool WaitUntilHealthy()
    {
        var deadline = DateTime.UtcNow.AddSeconds(HealthCheckTimeoutSeconds);
        while (DateTime.UtcNow < deadline)
        {
            if (IsAppResponding())
            {
                return true;
            }
            Thread.Sleep(500);
        }
        return false;
    }

    private static bool IsAppResponding()
    {
        try
        {
            var request = (HttpWebRequest)WebRequest.Create(AppUrl);
            request.Method = "HEAD";
            request.Timeout = 2000;
            request.AllowAutoRedirect = true;
            using (var response = (HttpWebResponse)request.GetResponse())
            {
                return (int)response.StatusCode < 500;
            }
        }
        catch (WebException ex)
        {
            // A redirect (e.g. to /setup or /login) still means the app
            // itself answered -- only treat a genuine connection failure as
            // "not up yet."
            var httpResponse = ex.Response as HttpWebResponse;
            return httpResponse != null && (int)httpResponse.StatusCode < 500;
        }
    }

    /// <summary>
    /// Opens Nizam's own native window (WebView2), blocking until the user
    /// closes it -- the launcher process's whole purpose from this point on
    /// is to host that one window. Apache/MariaDB are left running as
    /// services regardless of this window closing: they are shared,
    /// Windows-managed infrastructure, not children of this process, and
    /// the point of Automatic-startup services is exactly that closing one
    /// window should not force a slow service restart the next time Nizam
    /// opens.
    /// </summary>
    private static void RunNativeWindow()
    {
        if (!IsWebView2RuntimeAvailable())
        {
            ShowFriendlyError(
                "Nizam needs the Microsoft Edge WebView2 Runtime",
                "This component is normally already included in Windows 10 and 11. If you see this message, " +
                "try running Windows Update, then launch Nizam again. Contact your administrator if this " +
                "keeps happening."
            );
            return;
        }

        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        Application.Run(new NizamWindow());
    }

    private static bool IsWebView2RuntimeAvailable()
    {
        try
        {
            // Throws if no Evergreen WebView2 Runtime (or compatible fixed
            // version) is installed anywhere findable; returns a version
            // string otherwise. This is a detection call only -- it does
            // not create a WebView2 environment or window by itself.
            var version = CoreWebView2Environment.GetAvailableBrowserVersionString();
            return !string.IsNullOrEmpty(version);
        }
        catch (WebView2RuntimeNotFoundException)
        {
            return false;
        }
    }

    // --- Duplicate-launch: activate the existing window instead of opening a second one ---
    [DllImport("user32.dll")]
    private static extern bool SetProcessDPIAware();
    
    [DllImport("user32.dll")]
    private static extern IntPtr FindWindow(string lpClassName, string lpWindowName);

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);

    private const int SW_RESTORE = 9;

    private static void ActivateExistingWindow()
    {
        var handle = FindWindow(null, WindowTitle);
        if (handle != IntPtr.Zero)
        {
            ShowWindow(handle, SW_RESTORE);
            SetForegroundWindow(handle);
        }
    }

    private static void ShowFriendlyError(string title, string message)
    {
        MessageBox.Show(message, title, MessageBoxButtons.OK, MessageBoxIcon.Error);
    }

    /// <summary>
    /// Never the raw exception to the user (per the packaging brief: no PHP-
    /// stack-trace-style failures) -- a short technical line for whoever
    /// reads the log, nothing more.
    /// </summary>
    private static void LogStartupFailure(Exception ex)
    {
        try
        {
            var dataDir = Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData) + "\\Nizam\\storage\\logs";
            Directory.CreateDirectory(dataDir);
            var line = string.Format("[{0}] Launcher startup failure: {1}\r\n", DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"), ex.Message);
            File.AppendAllText(dataDir + "\\launcher.log", line);
        }
        catch (Exception)
        {
            // Never let a logging failure mask the original problem or crash the launcher.
        }
    }

    /// <summary>
    /// The native application window: a plain WinForms Form with a single
    /// WebView2 control docked to fill it. No address bar, no tabs, no
    /// browser menu -- WebView2's own default chrome is exactly nothing
    /// (it is an embeddable control, not a browser window), so there is
    /// nothing to strip out.
    /// </summary>
    private sealed class NizamWindow : Form
    {
        private readonly WebView2 _webView;

        public NizamWindow()
        {
            Text = WindowTitle;
            MinimumSize = new Size(1024, 700);
            Size = new Size(1280, 800);
            StartPosition = FormStartPosition.CenterScreen;
            ShowInTaskbar = true;

            // Use high-quality PNG for window icon instead of ICO
            var iconPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "hadaba-256.png");
            if (File.Exists(iconPath))
            {
                using (var bitmap = new System.Drawing.Bitmap(iconPath))
                {
                    Icon = System.Drawing.Icon.FromHandle(bitmap.GetHicon());
                }
            }

            _webView = new WebView2 { Dock = DockStyle.Fill };
            Controls.Add(_webView);

            Load += NizamWindow_Load;
        }

        private async void NizamWindow_Load(object sender, EventArgs e)
        {
            try
            {
                // A per-user, writable profile folder for WebView2's own
                // runtime state (cookies, cache) -- never Program Files,
                // which a standard user cannot write to, and never
                // ProgramData, which is shared school data, not UI cache.
                var userDataFolder = Path.Combine(
                    Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                    "Nizam", "WebView2");
                Directory.CreateDirectory(userDataFolder);

                var environment = await CoreWebView2Environment.CreateAsync(null, userDataFolder);
                await _webView.EnsureCoreWebView2Async(environment);

                // No context menu, no dev tools, no zoom controls, no
                // browser-style status bar -- a normal school user has no
                // use for any of them, and default-on inspection tools are
                // exactly the kind of "isn't this actually a browser"
                // impression the native window is meant to avoid.
                var settings = _webView.CoreWebView2.Settings;
                settings.AreDefaultContextMenusEnabled = false;
                settings.AreDevToolsEnabled = false;
                settings.IsZoomControlEnabled = false;
                settings.IsStatusBarEnabled = false;

                // Every navigation stays inside the same window -- a target=_blank
                // link (none exist in the app today, but this is defense in
                // depth) opens in this same view rather than spawning a
                // second, unmanaged window.
                _webView.CoreWebView2.NewWindowRequested += (s, args) =>
                {
                    args.Handled = true;
                    _webView.CoreWebView2.Navigate(args.Uri);
                };

                _webView.CoreWebView2.Navigate(AppUrl);
            }
            catch (Exception ex)
            {
                LogStartupFailure(ex);
                MessageBox.Show(
                    "Nizam's window could not be started. Try restarting your computer, and contact your " +
                    "administrator if this keeps happening.",
                    "Nizam could not start", MessageBoxButtons.OK, MessageBoxIcon.Error);
                Close();
            }
        }
    }
}
