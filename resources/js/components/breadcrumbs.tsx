import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Fragment } from 'react';

export function Breadcrumbs({ breadcrumbs }: { breadcrumbs: BreadcrumbItemType[] }) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb className="min-w-0">
                    {/*
                     * One line, never two: the trail sits in a 68px header, so
                     * wrapping would push it out of the bar. The ancestors keep
                     * their width and the current page truncates instead — and
                     * on a narrow viewport the ancestors drop away entirely,
                     * leaving the page name.
                     */}
                    <BreadcrumbList className="flex-nowrap overflow-hidden">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem className={isLast ? 'min-w-0' : 'hidden shrink-0 whitespace-nowrap lg:inline-flex'}>
                                        {isLast ? (
                                            <BreadcrumbPage className="truncate">{item.title}</BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink href={item.href} className="whitespace-nowrap">
                                                {item.title}
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator className="hidden shrink-0 lg:flex" />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
