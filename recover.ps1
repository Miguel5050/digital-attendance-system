$sh = New-Object -ComObject Shell.Application
$bin = $sh.NameSpace(10)
foreach ($item in $bin.Items()) {
    $name = $item.Name
    $path = $item.Path
    $type = $item.Type
    
    if ($name -match "(dashboard|migrate|login|index|logout|database)\.(php|sql)") {
        Write-Host "Restoring $name from $path"
        $bin.ParseName($name).InvokeVerb("undelete")
    }
}
Write-Host "Recovery Attempt Complete."
