; Nizam -- Windows installer (Inno Setup 6). Packages the existing, already-
; tested PHP/MariaDB/Apache application as a normal Windows product: no
; PHP/Composer/database-server knowledge required from the school.
;
; Architecture: Program Files\Nizam holds the application and a portable
; Apache+PHP+MariaDB runtime (all three read-mostly, replaced wholesale on
; an update); ProgramData\Nizam holds everything mutable -- the database,
; uploaded photos, the school logo, backups, settings, logs -- so an update
; or reinstall of the program files can never touch school data. Apache and
; MariaDB run as Windows services (Automatic startup) so the school never
; needs to start anything or see a UAC prompt for day-to-day use; the
; Launcher just health-checks and opens the browser.

#define MyAppName "Hadaba Al-Ahram School"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "Hadaba Al-Ahram Language School"
#define MyAppURL "http://127.0.0.1:8890/"
#define MyDataDirName "HadabaSchool"
#define ApacheServiceName "HadabaApache"
#define MariaDbServiceName "HadabaMariaDB"

[Setup]
AppId={{2F3B7B6E-7B5C-4A6D-9B0E-4C1F2A9E7D31}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName} {#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\{#MyAppName}
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputDir=..\dist
OutputBaseFilename=Hadaba-Al-Ahram-School-Setup-{#MyAppVersion}
SetupIconFile=assets\hadaba.ico
UninstallDisplayIcon={app}\HadabaLauncher.exe
Compression=lzma2/max
SolidCompression=yes
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
WizardStyle=modern
DisableWelcomePage=no
LicenseFile=
; Everything the installer needs travels inside the compiled .exe itself --
; no network access at install time (blueprint requirement: "the installer
; itself must not require Internet access").
VersionInfoVersion={#MyAppVersion}
VersionInfoProductName={#MyAppName}
VersionInfoProductVersion={#MyAppVersion}
VersionInfoCompany={#MyAppPublisher}
VersionInfoDescription=Hadaba Al-Ahram School Management System Setup

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
; --- Application (Program Files -- read-mostly, replaced on every update) ---
Source: "stage\app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "stage\runtime\*"; DestDir: "{app}\runtime"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\packaging\launcher\HadabaLauncher.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\packaging\launcher\Microsoft.Web.WebView2.Core.dll"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\packaging\launcher\Microsoft.Web.WebView2.WinForms.dll"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\packaging\launcher\WebView2Loader.dll"; DestDir: "{app}"; Flags: ignoreversion
Source: "assets\hadaba.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\packaging\apache\httpd-nizam.conf"; DestDir: "{app}\runtime-config"; Flags: ignoreversion
Source: "..\packaging\mysql\my-nizam.ini"; DestDir: "{app}\runtime-config"; Flags: ignoreversion
Source: "..\packaging\docs\User-Guide.pdf"; DestDir: "{app}\docs"; Flags: ignoreversion skipifsourcedoesntexist
Source: "..\packaging\docs\Administrator-Guide.pdf"; DestDir: "{app}\docs"; Flags: ignoreversion skipifsourcedoesntexist
Source: "..\packaging\docs\Release-Notes.pdf"; DestDir: "{app}\docs"; Flags: ignoreversion skipifsourcedoesntexist

[Dirs]
; --- Persistent school data (ProgramData -- never touched by an update) ---
; NB: Inno Setup only creates these on a fresh install by default; an
; upgrade over an existing installation leaves whatever is already there
; completely alone, which is exactly the required property.
Name: "{commonappdata}\{#MyDataDirName}\config"; Permissions: users-modify
Name: "{commonappdata}\{#MyDataDirName}\database\data"; Permissions: users-modify
Name: "{commonappdata}\{#MyDataDirName}\database\backups"; Permissions: users-modify
Name: "{commonappdata}\{#MyDataDirName}\storage\logs"; Permissions: users-modify
Name: "{commonappdata}\{#MyDataDirName}\storage\uploads"; Permissions: users-modify

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\HadabaLauncher.exe"; IconFilename: "{app}\hadaba.ico"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\HadabaLauncher.exe"; IconFilename: "{app}\hadaba.ico"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Run]
Filename: "{app}\HadabaLauncher.exe"; Description: "Launch {#MyAppName} now"; Flags: nowait postinstall skipifsilent

[UninstallDelete]
; Program-files side only -- ProgramData is handled explicitly in code
; (below) precisely so a plain uninstall can never silently take school
; data with it.
Type: filesandordirs; Name: "{app}\runtime-config"

[Code]
var
  DataDirExisted: Boolean;

function DataDir(): String;
begin
  Result := ExpandConstant('{commonappdata}\{#MyDataDirName}');
end;

function RuntimeDir(): String;
begin
  Result := ExpandConstant('{app}\runtime');
end;

function AppDir(): String;
begin
  Result := ExpandConstant('{app}\app');
end;

{ Inno Setup's Pascal Script has no ReplaceStr()-as-expression -- StringChangeEx
  only mutates a var in place, so this wraps it for inline use below. }
function ForwardSlashes(Path: String): String;
var
  S: String;
begin
  S := Path;
  StringChangeEx(S, '\', '/', True);
  Result := S;
end;

{ Writes a config template, replacing @@APP_DIR@@ / @@DATA_DIR@@ / @@RUNTIME_DIR@@
  with real, resolved absolute paths -- these are only known once the user's
  actual install location is final, so the shipped file is a template, never
  hand-edited afterward. Forward slashes throughout: both Apache and MariaDB
  on Windows accept them, and it avoids ini/conf backslash-escaping pitfalls. }
procedure WriteResolvedConfig(SourceFile, DestFile: String);
var
  Contents: AnsiString;
  Text: String;
begin
  LoadStringFromFile(SourceFile, Contents);
  Text := Contents;
  StringChangeEx(Text, '@@APP_DIR@@', ForwardSlashes(AppDir()), True);
  StringChangeEx(Text, '@@DATA_DIR@@', ForwardSlashes(DataDir()), True);
  StringChangeEx(Text, '@@RUNTIME_DIR@@', ForwardSlashes(RuntimeDir()), True);
  SaveStringToFile(DestFile, Text, False);
end;

function ServiceExists(ServiceName: String): Boolean;
var
  ResultCode: Integer;
begin
  Exec('sc.exe', 'query "' + ServiceName + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  Result := (ResultCode = 0);
end;

procedure StopAndRemoveService(ServiceName: String);
var
  ResultCode: Integer;
begin
  if ServiceExists(ServiceName) then
  begin
    Exec('sc.exe', 'stop "' + ServiceName + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
    Sleep(1500);
    Exec('sc.exe', 'delete "' + ServiceName + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
end;

function InitializeSetup(): Boolean;
begin
  DataDirExisted := DirExists(DataDir()) and FileExists(DataDir() + '\config\config.php');
  Result := True;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ResultCode: Integer;
  MariaDataDir: String;
begin
  if CurStep = ssPostInstall then
  begin
    // --- Resolve the two runtime config templates against the real install path ---
    WriteResolvedConfig(ExpandConstant('{app}\runtime-config\httpd-nizam.conf'),
                         RuntimeDir() + '\apache\conf\httpd.conf');
    WriteResolvedConfig(ExpandConstant('{app}\runtime-config\my-nizam.ini'),
                         RuntimeDir() + '\mysql\my.ini');

    // --- First install only: seed a clean MariaDB data directory ---
    // An upgrade (DataDirExisted = True) must never touch this -- the
    // school's real database already lives there. The seed is XAMPP/
    // MariaDB's own pristine "backup" data-directory template (system
    // tables only, no application data), staged under runtime\mysql-seed
    // at build time -- never this machine's live data directory.
    MariaDataDir := DataDir() + '\database\data';
    if not DataDirExisted then
    begin
      if not FileExists(MariaDataDir + '\mysql\user.MYD') then
      begin
        Exec(ExpandConstant('{cmd}'),
             '/C xcopy "' + RuntimeDir() + '\mysql-seed\*" "' + MariaDataDir + '\" /E /I /Q /Y',
             '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
      end;
    end;

    // --- Services: stop/remove any previous version's services first (safe
    // no-op on a fresh install), then (re)install pointing at the current
    // Program Files location, Automatic startup so the school never has to
    // start anything by hand. ---
    StopAndRemoveService('{#ApacheServiceName}');
    StopAndRemoveService('{#MariaDbServiceName}');

    Exec(RuntimeDir() + '\mysql\bin\mysqld.exe',
         '--install {#MariaDbServiceName} --defaults-file="' + RuntimeDir() + '\mysql\my.ini"',
         '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
    Exec(RuntimeDir() + '\apache\bin\httpd.exe',
         '-k install -n "{#ApacheServiceName}" -f "' + RuntimeDir() + '\apache\conf\httpd.conf"',
         '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

    Exec('sc.exe', 'config "{#MariaDbServiceName}" start= auto', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
    Exec('sc.exe', 'config "{#ApacheServiceName}" start= auto', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

    Exec('net.exe', 'start "{#MariaDbServiceName}"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
    Sleep(2000);

    // First install only: create the blank "nizam" database itself. The
    // Setup Wizard's own database step (unchanged from the original
    // README-documented deployment) has only ever assumed a blank target
    // database already exists -- normally a manual step for whoever has
    // phpMyAdmin/mysql access. A school installing this product has neither,
    // so the installer does it here instead of leaving the Setup Wizard's
    // very first step to fail with no way for a non-technical user to
    // recover.
    //
    // It also writes config.php itself, in the exact format
    // SetupController::writeConfigFile() produces: this installer already
    // knows the one fixed, correct connection (127.0.0.1:3319/nizam/root),
    // so SetupStatusService::currentStep() sees a working connection
    // immediately and skips the Setup Wizard's database-connection screen
    // entirely -- a school has no reason to ever see a database host/port
    // form. The Setup Wizard's own School/Year/Admin steps are untouched
    // and still run exactly as designed; this only pre-satisfies the one
    // step that was always meant for a technical installer, per README.md's
    // original deployment instructions, not an end school user.
    if not DataDirExisted then
    begin
      Exec(RuntimeDir() + '\mysql\bin\mysql.exe',
           '--protocol=TCP -h 127.0.0.1 -P 3319 -u root -e "CREATE DATABASE IF NOT EXISTS nizam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"',
           '', SW_HIDE, ewWaitUntilTerminated, ResultCode);

      SaveStringToFile(DataDir() + '\config\config.php',
        '<?php' + #13#10 +
        '/**' + #13#10 +
        ' * Nizam -- generated by the Windows installer at first install time,' + #13#10 +
        ' * in the exact format/location the Setup Wizard itself would write.' + #13#10 +
        ' */' + #13#10 +
        'return array (' + #13#10 +
        '  ''host'' => ''127.0.0.1'',' + #13#10 +
        '  ''port'' => 3319,' + #13#10 +
        '  ''database'' => ''nizam'',' + #13#10 +
        '  ''username'' => ''root'',' + #13#10 +
        '  ''password'' => '''',' + #13#10 +
        '  ''charset'' => ''utf8mb4'',' + #13#10 +
        ');' + #13#10,
        False);
    end;

    Exec('net.exe', 'start "{#ApacheServiceName}"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode);
  end;
end;

{ ---------------------------------------------------------------------
  Uninstall: the school's data must never be silently destroyed. The
  default is to KEEP it; only an explicit, clearly-labelled choice removes
  it. Services are always stopped and removed either way -- they are
  program infrastructure, not data. }
function ShouldRemoveData(): Boolean;
var
  Choice: Integer;
begin
  Choice := MsgBox(
    'Remove application only, and keep your school''s data (database, photos, backups, settings)?' + #13#10 + #13#10 +
    'Choose No to permanently delete everything, including the database and all backups.' + #13#10 + #13#10 +
    'Click Yes to KEEP your school data (recommended). Click No to remove EVERYTHING.',
    mbConfirmation, MB_YESNO);
  Result := (Choice = IDNO);
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
var
  RemoveEverything: Boolean;
begin
  if CurUninstallStep = usUninstall then
  begin
    StopAndRemoveService('{#ApacheServiceName}');
    StopAndRemoveService('{#MariaDbServiceName}');
  end;

  if CurUninstallStep = usPostUninstall then
  begin
    if DirExists(DataDir()) then
    begin
      RemoveEverything := ShouldRemoveData();
      if RemoveEverything then
      begin
        DelTree(DataDir(), True, True, True);
      end;
    end;
  end;
end;
