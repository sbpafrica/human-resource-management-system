import { useState } from 'react';
import { ChevronDown, X } from 'lucide-react';

interface Option {
  label: string;
  value: string | number;
}

interface MultiSelectProps {
  options: Option[];
  selected: (string | number)[];
  onChange: (selected: (string | number)[]) => void;
  placeholder?: string;
  className?: string;
}

export function MultiSelect({
  options,
  selected,
  onChange,
  placeholder = 'Select items...',
  className = '',
}: MultiSelectProps) {
  const [open, setOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');

  const selectedOptions = options.filter(o => selected.includes(o.value));
  const filteredOptions = options.filter(o =>
    String(o.label).toLowerCase().includes(searchTerm.toLowerCase())
  );

  const toggleOption = (value: string | number) => {
    if (selected.includes(value)) {
      onChange(selected.filter(v => v !== value));
    } else {
      onChange([...selected, value]);
    }
  };

  return (
    <div className={`relative ${className}`}>
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="w-full min-h-[42px] px-3 py-2 text-left border rounded-md bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 transition"
      >
        <div className="flex flex-wrap gap-1.5 flex-1 pr-2">
          {selectedOptions.length > 0 ? (
            selectedOptions.map(item => (
              <span
                key={item.value}
                className="inline-flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
              >
                {item.label}
                <X
                  className="w-3.5 h-3.5 cursor-pointer hover:text-blue-600"
                  onClick={(e) => {
                    e.stopPropagation();
                    onChange(selected.filter(v => v !== item.value));
                  }}
                />
              </span>
            ))
          ) : (
            <span className="text-gray-400 text-sm">{placeholder}</span>
          )}
        </div>
        <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform flex-shrink-0 ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-xl max-h-72 overflow-hidden flex flex-col">
          {/* Search Box */}
          <div className="p-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search..."
              className="w-full px-2.5 py-1 text-xs border rounded bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500"
              autoFocus
              onClick={(e) => e.stopPropagation()}
            />
          </div>

          {/* Options List */}
          <div className="overflow-y-auto max-h-60 p-1 divide-y divide-gray-100 dark:divide-gray-700/50">
            {filteredOptions.length > 0 ? (
              filteredOptions.map(option => {
                const isSelected = selected.includes(option.value);
                return (
                  <div
                    key={option.value}
                    onClick={() => toggleOption(option.value)}
                    className={`px-3 py-2 cursor-pointer flex items-center justify-between rounded hover:bg-blue-50 dark:hover:bg-blue-900/40 transition text-sm ${
                      isSelected ? 'bg-blue-50/80 font-medium text-blue-900 dark:text-blue-200' : 'text-gray-700 dark:text-gray-200'
                    }`}
                  >
                    <span>{option.label}</span>
                    <input
                      type="checkbox"
                      checked={isSelected}
                      readOnly
                      className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 pointer-events-none"
                    />
                  </div>
                );
              })
            ) : (
              <div className="p-3 text-center text-xs text-gray-500">No matching options found</div>
            )}
          </div>
        </div>
      )}

      {/* Close when clicking outside */}
      {open && <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />}
    </div>
  );
}
