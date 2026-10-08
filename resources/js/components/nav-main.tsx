// resources/js/components/nav-main.tsx
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

interface NavMainProps {
  items: NavItem[];
  itemClassName?: string;
  buttonClassName?: string;
  subClassName?: string;
  subItemClassName?: string;
  subButtonClassName?: string;
}

export function NavMain({
  items = [],
  itemClassName = '',
  buttonClassName = '',
  subClassName = '',
  subItemClassName = '',
  subButtonClassName = '',
}: NavMainProps) {
  const page = usePage();
  const [openItems, setOpenItems] = useState<{ [key: string]: boolean }>({});

  useEffect(() => {
    const checkChildActive = (child: NavItem): boolean => {
      if (child.href && page.url.startsWith(child.href)) return true;
      if (child.children?.some(checkChildActive)) return true;
      return false;
    };

    setOpenItems((prev) => {
      const next = { ...prev };
      let changed = false;

      items.forEach((item) => {
        if (item.children && item.children.some(checkChildActive)) {
          if (!next[item.title]) {
            next[item.title] = true;
            changed = true;
          }
          item.children.forEach((child) => {
            if (child.children && child.children.some(checkChildActive)) {
              if (!next[child.title]) {
                next[child.title] = true;
                changed = true;
              }
            }
          });
        }
      });

      return changed ? next : prev;
    });
  }, [page.url, items]);

  const toggleItem = (title: string) => {
    setOpenItems((prev) => ({ ...prev, [title]: !prev[title] }));
  };

  return (
    <SidebarGroup className="px-2 py-0">
      <SidebarGroupLabel>Platform</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item, index) => (
          <SidebarMenuItem key={`${item.title}-${index}`} className={itemClassName}>
            {item.children ? (
              <>
                <SidebarMenuButton
                  onClick={() => toggleItem(item.title)}
                  className={`flex items-center justify-between w-full h-auto min-h-10 py-2 ${buttonClassName}`}
                  isActive={item.href ? page.url.startsWith(item.href) : false}
                >
                  <div className="flex items-center flex-1 min-w-0">
                    {item.icon && <item.icon className="mr-2 h-4 w-4 flex-shrink-0" />}
                    <span className="whitespace-normal text-left break-words leading-tight">{item.title}</span>
                  </div>
                  {openItems[item.title] ? (
                    <ChevronDown className="h-4 w-4 flex-shrink-0 ml-2" />
                  ) : (
                    <ChevronRight className="h-4 w-4 flex-shrink-0 ml-2" />
                  )}
                </SidebarMenuButton>
                {openItems[item.title] && (
                  <SidebarMenuSub className={`pl-4 ${subClassName}`}>
                    {item.children.map((child, childIndex) => (
                      child.children ? (
                        <SidebarMenuSubItem
                          key={`${child.title}-${childIndex}`}
                          className={`my-1 ${subItemClassName}`}
                        >
                          <SidebarMenuSubButton
                            onClick={() => toggleItem(child.title)}
                            className={`flex items-center justify-between w-full h-auto min-h-8 py-1.5 pr-2 ${subButtonClassName}`}
                          >
                            <span className="whitespace-normal text-left break-words flex-1 min-w-0 leading-tight">
                              {child.title}
                            </span>
                            {openItems[child.title] ? (
                              <ChevronDown className="h-3 w-3 flex-shrink-0 ml-1" />
                            ) : (
                              <ChevronRight className="h-3 w-3 flex-shrink-0 ml-1" />
                            )}
                          </SidebarMenuSubButton>
                          {openItems[child.title] && (
                            <SidebarMenuSub className="pl-3 mt-1">
                              {child.children.map((subChild, subChildIndex) => (
                                subChild.href ? (
                                  <SidebarMenuSubButton
                                    key={`${subChild.title}-${subChildIndex}`}
                                    asChild
                                    className={`h-auto min-h-7 py-1 my-0.5 ${subButtonClassName}`}
                                    isActive={page.url.startsWith(subChild.href)}
                                  >
                                    <Link
                                      href={subChild.href}
                                      prefetch
                                      className="whitespace-normal text-left break-words leading-tight block"
                                    >
                                      {subChild.title}
                                    </Link>
                                  </SidebarMenuSubButton>
                                ) : (
                                  <SidebarMenuSubButton
                                    key={`${subChild.title}-${subChildIndex}`}
                                    className={`h-auto min-h-7 py-1 my-0.5 whitespace-normal text-left break-words leading-tight ${subButtonClassName}`}
                                  >
                                    {subChild.title}
                                  </SidebarMenuSubButton>
                                )
                              ))}
                            </SidebarMenuSub>
                          )}
                        </SidebarMenuSubItem>
                      ) : (
                        child.href && (
                          <SidebarMenuSubButton
                            key={`${child.title}-${childIndex}`}
                            asChild
                            className={`h-auto min-h-8 py-1.5 my-0.5 ${subButtonClassName}`}
                            isActive={page.url.startsWith(child.href)}
                          >
                            <Link
                              href={child.href}
                              prefetch
                              className="whitespace-normal text-left break-words leading-tight block"
                            >
                              {child.title}
                            </Link>
                          </SidebarMenuSubButton>
                        )
                      )
                    ))}
                  </SidebarMenuSub>
                )}
              </>
            ) : (
              item.href && (
                <SidebarMenuButton
                  asChild
                  className={`h-auto min-h-10 py-2 ${buttonClassName}`}
                  isActive={page.url.startsWith(item.href)}
                >
                  <Link href={item.href} prefetch className="flex items-center">
                    {item.icon && <item.icon className="mr-2 h-4 w-4 flex-shrink-0" />}
                    <span className="whitespace-normal text-left break-words leading-tight">{item.title}</span>
                  </Link>
                </SidebarMenuButton>
              )
            )}
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  );
}
