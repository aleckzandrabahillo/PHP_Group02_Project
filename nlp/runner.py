"""Own the invocation's process group before running the fixed local interpreter."""
import ctypes
import os
import runpy
import signal
import sys
from pathlib import Path


def own_processes():
    if os.name != "nt":
        os.setsid()

        def stop_group(_signum, _frame):
            os.killpg(os.getpgrp(), signal.SIGKILL)

        signal.signal(signal.SIGTERM, stop_group)
        return None

    from ctypes import wintypes

    class BasicLimits(ctypes.Structure):
        _fields_ = [("process_time", ctypes.c_longlong), ("job_time", ctypes.c_longlong),
                    ("flags", wintypes.DWORD), ("minimum_working_set", ctypes.c_size_t),
                    ("maximum_working_set", ctypes.c_size_t), ("active_processes", wintypes.DWORD),
                    ("affinity", ctypes.c_size_t), ("priority", wintypes.DWORD), ("scheduling", wintypes.DWORD)]

    class IoCounters(ctypes.Structure):
        _fields_ = [(name, ctypes.c_ulonglong) for name in
                    ("read_operations", "write_operations", "other_operations", "read_bytes", "write_bytes", "other_bytes")]

    class ExtendedLimits(ctypes.Structure):
        _fields_ = [("basic", BasicLimits), ("io", IoCounters),
                    ("process_memory", ctypes.c_size_t), ("job_memory", ctypes.c_size_t),
                    ("peak_process_memory", ctypes.c_size_t), ("peak_job_memory", ctypes.c_size_t)]

    kernel = ctypes.WinDLL("kernel32", use_last_error=True)
    kernel.CreateJobObjectW.argtypes = [ctypes.c_void_p, wintypes.LPCWSTR]
    kernel.CreateJobObjectW.restype = wintypes.HANDLE
    kernel.SetInformationJobObject.argtypes = [wintypes.HANDLE, ctypes.c_int, ctypes.c_void_p, wintypes.DWORD]
    kernel.SetInformationJobObject.restype = wintypes.BOOL
    kernel.GetCurrentProcess.restype = wintypes.HANDLE
    kernel.AssignProcessToJobObject.argtypes = [wintypes.HANDLE, wintypes.HANDLE]
    kernel.AssignProcessToJobObject.restype = wintypes.BOOL
    kernel.CloseHandle.argtypes = [wintypes.HANDLE]
    job = kernel.CreateJobObjectW(None, None)  # Unnamed, non-inheritable, owned only by this runner.
    limits = ExtendedLimits()
    limits.basic.flags = 0x2000  # JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE; children cannot break away.
    if not job:
        raise OSError("Process containment unavailable")
    if not kernel.SetInformationJobObject(job, 9, ctypes.byref(limits), ctypes.sizeof(limits)):
        kernel.CloseHandle(job)
        raise OSError("Process containment unavailable")
    if not kernel.AssignProcessToJobObject(job, kernel.GetCurrentProcess()):
        kernel.CloseHandle(job)
        raise OSError("Process containment unavailable")
    return job  # Keep the sole handle open until OS process exit; timeout closes it and kills descendants.


if __name__ == "__main__":
    try:
        job_handle = own_processes()
        sys.stdout.reconfigure(encoding="utf-8")
        runpy.run_path(str(Path(__file__).with_name("interpret.py")), run_name="__main__")
    except Exception:
        # No tracebacks, raw input or concern descriptions in stderr or logs.
        raise SystemExit(1) from None
