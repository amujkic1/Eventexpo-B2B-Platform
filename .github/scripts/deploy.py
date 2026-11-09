"""Upload the plugin using explicit FTPS; preserve unrelated remote files."""

import os
from ftplib import FTP_TLS, error_perm
from pathlib import Path
import ssl


def main():
    required = ("FTP_HOST", "FTP_USERNAME", "FTP_PASSWORD", "FTP_REMOTE_PATH")
    missing = [name for name in required if not os.environ.get(name)]
    if missing:
        raise SystemExit("Missing GitHub Actions secrets: " + ", ".join(missing))

    root = Path(__file__).resolve().parents[2]
    files = [root / "eventexpo-b2b-core.php"]
    for directory in ("assets", "includes", "templates"):
        files.extend(sorted(p for p in (root / directory).rglob("*") if p.is_file()))
    files = [p for p in files if not any(part.startswith(".") for part in p.relative_to(root).parts)]
    if any(p.is_symlink() or not p.resolve().is_relative_to(root) for p in files):
        raise SystemExit("Deployment files must stay inside the project.")

    with FTP_TLS(context=ssl.create_default_context(), timeout=60) as ftp:
        ftp.connect(os.environ["FTP_HOST"], int(os.environ.get("FTP_PORT") or "21"))
        ftp.login(os.environ["FTP_USERNAME"], os.environ["FTP_PASSWORD"])
        ftp.prot_p()
        # Require an existing destination to avoid deploying into an accidental path.
        ftp.cwd(os.environ["FTP_REMOTE_PATH"])
        destination = ftp.pwd()
        for path in files:
            relative = path.relative_to(root)
            ftp.cwd(destination)
            for directory in relative.parts[:-1]:
                try:
                    ftp.cwd(directory)
                except error_perm:
                    # If creation fails, stop rather than uploading in the wrong folder.
                    ftp.mkd(directory)
                    ftp.cwd(directory)
            with path.open("rb") as source:
                ftp.storbinary("STOR " + path.name, source)
            print("Uploaded " + relative.as_posix())
        print(f"Deployment complete: {len(files)} files.")


if __name__ == "__main__":
    main()
