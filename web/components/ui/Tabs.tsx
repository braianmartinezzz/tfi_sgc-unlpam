"use client";

import {
  useEffect,
  useId,
  useRef,
  type KeyboardEvent,
  type ReactNode,
} from "react";

export interface TabOption {
  value: string;
  label: string;
  disabled?: boolean;
  disabledReason?: string;
}

interface TabsProps {
  label: string;
  tabs: TabOption[];
  value: string;
  onChange: (value: string) => void;
  children: ReactNode;
  focusSelectedOnMount?: boolean;
}

export default function Tabs({
  label,
  tabs,
  value,
  onChange,
  children,
  focusSelectedOnMount = false,
}: TabsProps) {
  const id = useId();
  const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const selectedTab = tabs.find((tab) => tab.value === value && !tab.disabled);
  const activeTab = selectedTab ?? tabs.find((tab) => !tab.disabled);
  const activeTabIndex = activeTab
    ? tabs.findIndex((tab) => tab.value === activeTab.value)
    : -1;

  useEffect(() => {
    if (!focusSelectedOnMount || activeTabIndex < 0) return;
    tabRefs.current[activeTabIndex]?.focus();
  }, [activeTabIndex, focusSelectedOnMount]);

  if (!activeTab) return null;

  function focusRelativeTab(currentIndex: number, direction: -1 | 1) {
    let nextIndex = currentIndex;

    for (let checked = 0; checked < tabs.length; checked += 1) {
      nextIndex = (nextIndex + direction + tabs.length) % tabs.length;
      if (!tabs[nextIndex].disabled) {
        onChange(tabs[nextIndex].value);
        tabRefs.current[nextIndex]?.focus();
        return;
      }
    }
  }

  function handleKeyDown(
    event: KeyboardEvent<HTMLButtonElement>,
    index: number,
  ) {
    if (event.key === "ArrowRight") {
      event.preventDefault();
      focusRelativeTab(index, 1);
    } else if (event.key === "ArrowLeft") {
      event.preventDefault();
      focusRelativeTab(index, -1);
    } else if (event.key === "Home") {
      event.preventDefault();
      const firstEnabled = tabs.findIndex((tab) => !tab.disabled);
      if (firstEnabled >= 0) {
        onChange(tabs[firstEnabled].value);
        tabRefs.current[firstEnabled]?.focus();
      }
    } else if (event.key === "End") {
      event.preventDefault();
      const lastEnabled = tabs.findLastIndex((tab) => !tab.disabled);
      if (lastEnabled >= 0) {
        onChange(tabs[lastEnabled].value);
        tabRefs.current[lastEnabled]?.focus();
      }
    }
  }

  return (
    <div>
      <div
        role="tablist"
        aria-label={label}
        className="flex flex-wrap gap-2 border-b border-ui-border"
      >
        {tabs.map((tab, index) => {
          const tabId = `${id}-tab-${tab.value}`;
          const panelId = `${id}-panel-${tab.value}`;
          const isSelected = tab.value === activeTab.value;

          return (
            <button
              key={tab.value}
              ref={(element) => {
                tabRefs.current[index] = element;
              }}
              type="button"
              role="tab"
              id={tabId}
              aria-selected={isSelected}
              aria-controls={panelId}
              aria-disabled={tab.disabled || undefined}
              tabIndex={isSelected ? 0 : -1}
              onClick={() => {
                if (!tab.disabled) onChange(tab.value);
              }}
              onKeyDown={(event) => handleKeyDown(event, index)}
              className={`min-h-11 rounded-t-md border-b-2 px-4 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                isSelected
                  ? "border-brand text-brand-text"
                  : "border-transparent text-foreground hover:bg-row-alternate"
              } ${tab.disabled ? "cursor-not-allowed opacity-75" : ""}`}
            >
              <span className="block">{tab.label}</span>
              {tab.disabledReason ? (
                <span className="block text-xs font-normal">
                  {tab.disabledReason}
                </span>
              ) : null}
            </button>
          );
        })}
      </div>
      {tabs.map((tab) => (
        <div
          key={tab.value}
          role="tabpanel"
          id={`${id}-panel-${tab.value}`}
          aria-labelledby={`${id}-tab-${tab.value}`}
          tabIndex={tab.value === activeTab.value ? 0 : -1}
          hidden={tab.value !== activeTab.value}
          className="pt-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
        >
          {tab.value === activeTab.value ? children : null}
        </div>
      ))}
    </div>
  );
}
