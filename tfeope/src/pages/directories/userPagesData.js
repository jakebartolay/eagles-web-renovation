export const slugifyUserValue = (value) =>
  String(value || '')
    .toLowerCase()
    .replace(/^gov\.?\s+/i, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const normalizeTextKey = (value) =>
  String(value || '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

const isInternationalRegion = (value) =>
  /(europe|european|schengen|singapore|thailand|malta|italy|greece|roma|milan|bologna|parma)/i.test(String(value || ''));

const getMemberClubName = (member) => member?.club || member?.clubName || member?.club_name || '';
const getMemberRegionName = (member) => member?.region || member?.regionName || member?.region_name || '';

const getGovernorName = (governor) =>
  governor?.name || governor?.governorName || governor?.governor_name || governor?.governor || '';

const getRegionName = (region) => region?.name || region?.regionName || region?.region_name || '';

const countMembersForClub = (memberRows, clubName) => {
  const clubKey = normalizeTextKey(clubName);
  if (!clubKey) return 0;

  return memberRows.filter((member) => normalizeTextKey(member.club) === clubKey).length;
};

export const normalizeMemberRows = (rows = []) =>
  rows
    .map((member) => {
      const firstName = member?.firstName || member?.first_name || '';
      const lastName = member?.lastName || member?.last_name || '';
      const fullName = member?.fullName || member?.name || [lastName, firstName].filter(Boolean).join(', ');
      const status = member?.idStatus || member?.id_status || member?.status || '';
      const position = member?.position || member?.clubPosition || member?.club_position || '';

      return {
        id: String(member?.id || member?.eagles_id || ''),
        name: fullName || 'Not available',
        position: position || 'Not available',
        positionKey: slugifyUserValue(position) || 'not-available',
        status: status || 'Not available',
        statusKey: slugifyUserValue(status) || 'not-available',
        club: getMemberClubName(member),
        region: getMemberRegionName(member),
        raw: member,
      };
    })
    .filter((member) => member.id || member.name !== 'Not available' || member.club || member.region);

const normalizeClubFromApi = (club, context = {}, memberRows = []) => {
  const name = club?.name || club?.clubName || club?.club_name || '';
  if (!name) return null;

  const memberCount = Number(club?.memberCount || club?.member_count || 0) || countMembersForClub(memberRows, name);

  return {
    slug: club?.slug || slugifyUserValue(name),
    id: club?.id ?? club?.club_id ?? null,
    name,
    region: context.region || club?.region || club?.regionName || '',
    regionId: club?.regionId ?? club?.region_id ?? context.regionId ?? null,
    governorId: club?.governorId ?? club?.governor_id ?? context.governorId ?? null,
    governorSlug: context.governorSlug || '',
    governorName: context.governorName || club?.governorName || club?.governor_name || '',
    memberCount,
    deliveredCount: Number(club?.deliveredCount || club?.delivered_count || 0),
  };
};

export const normalizeGovernorsFromApi = (governorRows = [], memberRows = []) =>
  governorRows
    .map((governor) => {
      const name = getGovernorName(governor);
      if (!name) return null;

      const regions = Array.isArray(governor?.regions) ? governor.regions : [];
      const regionNames = regions.map(getRegionName).filter(Boolean);
      const nestedClubs = regions.flatMap((region) => (Array.isArray(region?.clubs) ? region.clubs : []));
      const regionLabel = regionNames.join(' / ') || governor?.region || governor?.regionName || '';
      const clubCount = nestedClubs.length || Number(governor?.clubCount || governor?.club_count || 0);
      const memberCount = regionNames.reduce((sum, regionName) => {
        const regionKey = normalizeTextKey(regionName);
        return sum + memberRows.filter((member) => normalizeTextKey(member.region) === regionKey).length;
      }, 0);

      return {
        slug: governor?.slug || slugifyUserValue(name),
        id: governor?.id ?? governor?.governor_id ?? null,
        name,
        region: regionLabel,
        location: regionNames.some(isInternationalRegion) || isInternationalRegion(regionLabel) ? 'intl' : 'ph',
        clubCount,
        memberCount,
        term: governor?.term || '',
        imageUrl: governor?.imageUrl || governor?.image_url || governor?.photoUrl || governor?.photo_url || '',
      };
    })
    .filter(Boolean);

export const buildRegionalClubGroupsFromApi = (governorRows = [], clubRows = [], memberRows = []) => {
  const normalizedMembers = normalizeMemberRows(memberRows);
  const groupsByRegion = new Map();
  const governorById = new Map();
  const regionById = new Map();
  const catalogedRegionKeysByClubKey = new Map();
  const unassignedRegionKey = normalizeTextKey('Unassigned Region');

  const rememberCatalogedClubRegion = (club, regionName) => {
    const clubKey = normalizeTextKey(club?.name);
    const regionKey = normalizeTextKey(regionName);
    if (!clubKey || !regionKey || regionKey === unassignedRegionKey) return;

    if (!catalogedRegionKeysByClubKey.has(clubKey)) {
      catalogedRegionKeysByClubKey.set(clubKey, new Set());
    }

    catalogedRegionKeysByClubKey.get(clubKey).add(regionKey);
  };

  const ensureGroup = ({ region, governorName = '', governorSlug = '', governorId = null, location = 'ph' }) => {
    const regionName = region || 'Unassigned Region';
    const key = normalizeTextKey(regionName) || slugifyUserValue(regionName);
    const current = groupsByRegion.get(key);
    if (current) {
      if (!current.governorName && governorName) current.governorName = governorName;
      if (!current.governorSlug && governorSlug) current.governorSlug = governorSlug;
      if (!current.governorId && governorId) current.governorId = governorId;
      return current;
    }

    const group = {
      slug: governorSlug || slugifyUserValue(regionName),
      region: regionName,
      governorName,
      governorSlug: governorSlug || slugifyUserValue(governorName || regionName),
      governorId,
      location,
      clubCount: 0,
      memberCount: 0,
      clubs: [],
    };

    groupsByRegion.set(key, group);
    return group;
  };

  governorRows.forEach((governor) => {
    const governorName = getGovernorName(governor);
    if (!governorName) return;

    const governorId = governor?.id ?? governor?.governor_id ?? null;
    const governorSlug = governor?.slug || slugifyUserValue(governorName);
    if (governorId !== null) {
      governorById.set(Number(governorId), { governorName, governorSlug, governorId });
    }

    const regions = Array.isArray(governor?.regions) ? governor.regions : [];
    regions.forEach((region) => {
      const regionName = getRegionName(region);
      const group = ensureGroup({
        region: regionName,
        governorName,
        governorSlug,
        governorId,
        location: isInternationalRegion(regionName) ? 'intl' : 'ph',
      });
      const regionId = region?.id ?? region?.region_id ?? null;
      if (regionId !== null) {
        regionById.set(Number(regionId), {
          region: group.region,
          governorName,
          governorSlug,
          governorId,
        });
      }

      const nestedClubs = Array.isArray(region?.clubs) ? region.clubs : [];
      nestedClubs.forEach((club) => {
        const normalizedClub = normalizeClubFromApi(
          club,
          { region: group.region, regionId, governorName, governorSlug, governorId },
          normalizedMembers,
        );
        if (
          normalizedClub &&
          !group.clubs.some((item) => normalizeTextKey(item.name) === normalizeTextKey(normalizedClub.name))
        ) {
          group.clubs.push(normalizedClub);
        }
        rememberCatalogedClubRegion(normalizedClub, group.region);
      });
    });
  });

  clubRows.forEach((club) => {
    const regionId = Number(club?.regionId ?? club?.region_id ?? 0);
    const governorId = Number(club?.governorId ?? club?.governor_id ?? 0);
    const regionContext = regionById.get(regionId);
    const governorContext = governorById.get(governorId) || regionContext || {};
    const regionName = regionContext?.region || club?.region || club?.regionName || 'Unassigned Region';
    const group = ensureGroup({
      region: regionName,
      governorName: governorContext.governorName || club?.governorName || club?.governor_name || '',
      governorSlug: governorContext.governorSlug || '',
      governorId: governorContext.governorId || governorId || null,
      location: isInternationalRegion(regionName) ? 'intl' : 'ph',
    });
    const normalizedClub = normalizeClubFromApi(
      club,
      {
        region: group.region,
        regionId,
        governorName: group.governorName,
        governorSlug: group.governorSlug,
        governorId: group.governorId,
      },
      normalizedMembers,
    );

    if (
      normalizedClub &&
      !group.clubs.some((item) => normalizeTextKey(item.name) === normalizeTextKey(normalizedClub.name))
    ) {
      group.clubs.push(normalizedClub);
    }
    rememberCatalogedClubRegion(normalizedClub, group.region);
  });

  normalizedMembers.forEach((member) => {
    const regionName = member.region || 'Unassigned Region';
    const clubName = member.club;
    if (!clubName) return;

    const group = ensureGroup({
      region: regionName,
      location: isInternationalRegion(regionName) ? 'intl' : 'ph',
    });
    const clubKey = normalizeTextKey(clubName);
    const catalogedRegionKeys = catalogedRegionKeysByClubKey.get(clubKey);
    if (catalogedRegionKeys?.size && !catalogedRegionKeys.has(normalizeTextKey(group.region))) {
      return;
    }

    const existingClub = group.clubs.find((item) => normalizeTextKey(item.name) === normalizeTextKey(clubName));
    if (existingClub) {
      existingClub.memberCount = Math.max(existingClub.memberCount || 0, countMembersForClub(normalizedMembers, clubName));
      return;
    }

    const normalizedClub = normalizeClubFromApi(
      { name: clubName },
      {
        region: group.region,
        governorName: group.governorName,
        governorSlug: group.governorSlug,
        governorId: group.governorId,
      },
      normalizedMembers,
    );

    if (normalizedClub) {
      group.clubs.push(normalizedClub);
    }
  });

  return Array.from(groupsByRegion.values())
    .map((group) => {
      const memberCount = group.clubs.reduce((sum, club) => sum + Number(club.memberCount || 0), 0);
      return {
        ...group,
        clubCount: group.clubs.length,
        memberCount,
        clubs: group.clubs.slice().sort((a, b) => a.name.localeCompare(b.name)),
      };
    })
    .sort((a, b) => a.region.localeCompare(b.region));
};

export const findClubBySlugInGroups = (groups, slug) =>
  groups.flatMap((group) => group.clubs).find((club) => club.slug === slug) || null;

export const membersForClub = (memberRows, clubName) => {
  const clubKey = normalizeTextKey(clubName);
  if (!clubKey) return [];

  return normalizeMemberRows(memberRows).filter((member) => normalizeTextKey(member.club) === clubKey);
};

export const buildStatusSummary = (memberRows = []) => {
  const statusCounts = new Map();

  normalizeMemberRows(memberRows).forEach((member) => {
    const label = member.status || 'Not available';
    const key = member.statusKey || slugifyUserValue(label) || 'not-available';
    const current = statusCounts.get(key) || { key, label, count: 0 };
    statusCounts.set(key, { ...current, count: current.count + 1 });
  });

  return Array.from(statusCounts.values()).sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));
};
