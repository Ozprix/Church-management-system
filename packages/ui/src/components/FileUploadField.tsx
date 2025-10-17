import { ChangeEvent, useId, useRef } from 'react';
import { classNames } from '../utils/classNames';
import { Button } from './Button';

export interface FileUploadFieldProps {
  label?: string;
  description?: string;
  fileName?: string | null;
  fileSize?: number | null;
  downloadUrl?: string | null;
  accept?: string;
  disabled?: boolean;
  uploading?: boolean;
  error?: string | null;
  className?: string;
  onSelectFile: (file: File) => void;
  onRemove?: () => void;
  buttonLabel?: string;
}

function formatBytes(size?: number | null): string | null {
  if (!size || size <= 0) {
    return null;
  }

  const units = ['B', 'KB', 'MB', 'GB'];
  let index = 0;
  let value = size;
  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }

  return `${value.toFixed(1)} ${units[index]}`;
}

export function FileUploadField({
  label,
  description,
  fileName,
  fileSize,
  downloadUrl,
  accept,
  disabled,
  uploading,
  error,
  className,
  onSelectFile,
  onRemove,
  buttonLabel = 'Upload file',
}: FileUploadFieldProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const id = useId();

  const handlePickFile = () => {
    if (disabled || uploading) return;
    inputRef.current?.click();
  };

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      onSelectFile(file);
      event.target.value = '';
    }
  };

  const sizeLabel = formatBytes(fileSize);

  return (
    <div className={classNames('space-y-2', className)}>
      <input
        ref={inputRef}
        id={id}
        type="file"
        accept={accept}
        className="hidden"
        onChange={handleFileChange}
      />
      {label ? <p className="text-sm font-medium text-slate-700">{label}</p> : null}
      {description ? <p className="text-xs text-slate-500">{description}</p> : null}
      <div className="flex flex-wrap items-center gap-3">
        <Button type="button" variant="secondary" onClick={handlePickFile} disabled={disabled || uploading}>
          {uploading ? 'Uploading…' : buttonLabel}
        </Button>
        {fileName ? (
          <div className="flex items-center gap-2 text-sm text-slate-600">
            <span className="font-medium text-slate-800">{fileName}</span>
            {sizeLabel ? <span className="text-xs text-slate-500">({sizeLabel})</span> : null}
            {downloadUrl ? (
              <a
                href={downloadUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="text-emerald-600 hover:text-emerald-700"
              >
                View
              </a>
            ) : null}
            {onRemove ? (
              <button
                type="button"
                onClick={onRemove}
                className="text-emerald-600 hover:text-emerald-700"
              >
                Remove
              </button>
            ) : null}
          </div>
        ) : (
          <span className="text-sm text-slate-500">No file selected.</span>
        )}
      </div>
      {error ? <p className="text-xs text-rose-600">{error}</p> : null}
    </div>
  );
}
