Set WshShell = CreateObject("WScript.Shell")
WshShell.Run chr(34) & "daemon_curl.bat" & Chr(34), 0
WshShell.Run chr(34) & "daemon_wa.bat" & Chr(34), 0
Set WshShell = Nothing
